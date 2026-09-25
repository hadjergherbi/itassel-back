<?php

namespace Tests\Feature;

use App\Models\Doleance;
use App\Models\Journal;
use App\Models\PieceJointe;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class SecuriteTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Cache::flush();
        $this->travel(61)->seconds();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->travelBack();
        parent::tearDown();
    }

    public function test_le_login_renvoie_expire_le_et_un_jeton_expire_renvoie_401(): void
    {
        $this->superAdmin(['email' => 'expire@itassel.test']);

        $reponse = $this->postJson('/api/admin/login', [
            'email'        => 'expire@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertOk()->assertJsonStructure(['token', 'utilisateur', 'expire_le']);

        $this->assertNotEmpty($reponse->json('expire_le'));
        $this->assertNotFalse(strtotime((string) $reponse->json('expire_le')));

        $token = $reponse->json('token');
        Carbon::setTestNow(now()->addHours(9));

        $this->withToken($token)
            ->getJson('/api/admin/me')
            ->assertStatus(401)
            ->assertJson(['message' => 'Session expirée. Veuillez vous reconnecter.']);
    }

    public function test_cinq_echecs_verrouillent_meme_avec_le_bon_mot_de_passe(): void
    {
        $this->superAdmin(['email' => 'verrou@itassel.test']);

        $this->echouerLogin(5, 'verrou@itassel.test', 'mauvais');
        $this->travel(61)->seconds();

        $bloque = $this->postJson('/api/admin/login', [
            'email'        => 'verrou@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ]);

        $this->assertVerrouillage($bloque);
    }

    public function test_un_email_inexistant_est_verrouille_de_la_meme_facon(): void
    {
        $this->echouerLogin(5, 'absent.verrou@itassel.test', 'quelconque');
        $this->travel(61)->seconds();

        $bloque = $this->postJson('/api/admin/login', [
            'email'        => 'absent.verrou@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ]);

        $this->assertVerrouillage($bloque);
    }

    public function test_apres_quinze_minutes_la_connexion_est_de_nouveau_possible(): void
    {
        $this->superAdmin(['email' => 'attendre@itassel.test']);

        $this->echouerLogin(5, 'attendre@itassel.test', 'mauvais');
        $this->travel(15)->minutes();

        $this->postJson('/api/admin/login', [
            'email'        => 'attendre@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertOk()->assertJsonStructure(['token', 'expire_le']);
    }

    public function test_un_succes_remet_le_compteur_d_echecs_a_zero(): void
    {
        $this->superAdmin(['email' => 'reset@itassel.test']);

        $this->echouerLogin(2, 'reset@itassel.test', 'mauvais');

        $this->postJson('/api/admin/login', [
            'email'        => 'reset@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertOk();

        $this->travel(61)->seconds();
        $this->echouerLogin(5, 'reset@itassel.test', 'mauvais');
        $this->travel(61)->seconds();

        $this->postJson('/api/admin/login', [
            'email'        => 'reset@itassel.test',
            'mot_de_passe' => 'mauvais',
        ])->assertStatus(429);
    }

    public function test_les_echecs_et_le_verrouillage_sont_journalises_sans_mot_de_passe(): void
    {
        $this->superAdmin(['email' => 'journal.login@itassel.test']);

        $this->echouerLogin(5, 'journal.login@itassel.test', 'SecretInterdit99');

        $echecs = Journal::where('action', 'connexion_echec')->get();
        $this->assertCount(5, $echecs);

        foreach ($echecs as $ligne) {
            $this->assertSame('echec', $ligne->resultat);
            $this->assertStringContainsString('Email saisi :', (string) $ligne->detail);
            $this->assertStringContainsString('***', (string) $ligne->detail);
            $this->assertStringNotContainsString('SecretInterdit99', (string) $ligne->detail);
            $this->assertStringNotContainsString('mot_de_passe', mb_strtolower((string) $ligne->detail));
            $this->assertNotNull($ligne->adresse_ip);
        }

        $verrou = Journal::where('action', 'compte_verrouille')->get();
        $this->assertCount(1, $verrou);
        $this->assertSame('echec', $verrou->first()->resultat);
        $this->assertStringContainsString('***', (string) $verrou->first()->detail);
        $this->assertStringNotContainsString('SecretInterdit99', (string) $verrou->first()->detail);
    }

    public function test_le_depot_refuse_le_honeypot_et_un_jeton_invalide(): void
    {
        $message = "Votre demande n'a pas pu être envoyée. Rechargez la page et réessayez.";

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'site_web' => 'https://spam.test',
        ]))->assertStatus(422)->assertJson(['message' => $message]);
        $this->assertSame(0, Doleance::count());

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'jeton_formulaire' => '',
        ]))->assertStatus(422)->assertJson(['message' => $message]);

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'jeton_formulaire' => 'jeton-invalide',
        ]))->assertStatus(422)->assertJson(['message' => $message]);

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'jeton_formulaire' => Crypt::encryptString((string) now()->timestamp),
        ]))->assertStatus(422)->assertJson(['message' => $message]);

        $this->assertSame(0, Doleance::count());
    }

    public function test_le_depot_reussit_avec_un_jeton_valide(): void
    {
        $this->getJson('/api/formulaire/jeton')
            ->assertOk()
            ->assertJsonStructure(['jeton']);

        $this->postJson('/api/doleances', $this->champsDepotPublic())
            ->assertCreated()
            ->assertJsonStructure(['reference', 'message']);

        $this->assertSame(1, Doleance::count());
    }

    public function test_les_en_tetes_de_securite_sont_presents_sur_json_et_export(): void
    {
        $json = $this->getJson('/api/referentiels')->assertOk();
        $this->assertEnTetesSecurite($json);
        $this->assertNull($json->headers->get('X-Powered-By'));

        $this->doleance(['date_depot' => '2026-06-15']);
        $this->connecter($this->superAdmin());
        $export = $this->get('/api/admin/doleances/export?date_debut=2026-01-01&date_fin=2026-09-24')
            ->assertOk();
        $this->assertEnTetesSecurite($export);
        $this->assertStringContainsString('attachment', (string) $export->headers->get('Content-Disposition'));
        $this->assertNull($export->headers->get('Strict-Transport-Security'));
    }

    public function test_la_piece_jointe_est_servie_en_attachment_sans_exif(): void
    {
        $doleance = $this->doleance();
        $chemin = 'pieces-jointes/'.$doleance->id_doleance.'/'.uniqid('pj_', true).'.jpg';
        Storage::disk('local')->put($chemin, 'contenu-test');
        $piece = PieceJointe::create([
            'nom_fichier' => 'rapport.jpg',
            'type'        => 'jpg',
            'taille'      => 12,
            'chemin'      => $chemin,
            'origine'     => 'DEPOT_INITIAL',
            'id_doleance' => $doleance->id_doleance,
        ]);

        $this->connecter($this->superAdmin());
        $telechargement = $this->get("/api/admin/pieces-jointes/{$piece->id_piece}/telecharger")
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $telechargement->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('inline', (string) $telechargement->headers->get('Content-Disposition'));
        $this->assertSame('image/jpeg', $telechargement->headers->get('Content-Type'));
        $this->assertSame('nosniff', $telechargement->headers->get('X-Content-Type-Options'));

        $cheminExif = sys_get_temp_dir().DIRECTORY_SEPARATOR.'itassel-exif-'.uniqid('', true).'.jpg';
        $jpegExif = $this->jpegAvecExif();
        file_put_contents($cheminExif, $jpegExif);
        $this->assertStringContainsString('Exif', $jpegExif);

        $depot = $this->post('/api/doleances', $this->champsDepotPublic([
            'piece_jointe' => new UploadedFile($cheminExif, 'photo.jpg', 'image/jpeg', null, true),
        ]), ['Accept' => 'application/json']);

        if (! extension_loaded('gd')) {
            $depot->assertStatus(422)->assertJson(['message' => 'Image invalide.']);
            $this->assertFalse(PieceJointe::where('nom_fichier', 'photo.jpg')->exists());
            @unlink($cheminExif);

            return;
        }

        $depot->assertCreated();

        $stockee = PieceJointe::where('nom_fichier', 'photo.jpg')->first();
        $this->assertNotNull($stockee);
        $this->assertTrue(Storage::disk('local')->exists($stockee->chemin));
        $this->assertStringNotContainsString('photo.jpg', $stockee->chemin);

        $contenu = Storage::disk('local')->get($stockee->chemin);
        $this->assertStringNotContainsString('Exif', $contenu);
        $this->assertStringNotContainsString('48.8566', $contenu);
        @unlink($cheminExif);
    }

    private function echouerLogin(int $fois, string $email, string $motDePasse): void
    {
        for ($i = 0; $i < $fois; $i++) {
            $this->postJson('/api/admin/login', [
                'email'        => $email,
                'mot_de_passe' => $motDePasse,
            ])->assertStatus(422);
        }
    }

    private function assertVerrouillage($reponse): void
    {
        $reponse->assertStatus(429)
            ->assertJsonStructure(['message', 'reessayer_dans']);
        $this->assertMatchesRegularExpression(
            '/^Trop de tentatives\. Réessayez dans \d+ minutes\.$/',
            $reponse->json('message')
        );
        $this->assertIsInt($reponse->json('reessayer_dans'));
        $this->assertGreaterThan(0, $reponse->json('reessayer_dans'));
    }

    private function assertEnTetesSecurite($reponse): void
    {
        $reponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $reponse->assertHeader('X-Frame-Options', 'DENY');
        $reponse->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $reponse->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    private function jpegAvecExif(): string
    {
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGf/9k='
        );
        $exif = 'Exif'."\x00\x00".'GPS:48.8566,2.3522';
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return "\xFF\xD8".$app1.substr($jpeg, 2);
    }
}

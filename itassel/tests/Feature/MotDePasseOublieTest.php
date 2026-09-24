<?php

namespace Tests\Feature;

use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\Journal;
use App\Models\JetonMotDePasse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class MotDePasseOublieTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    private const MESSAGE_NEUTRE = 'Si un compte correspond à cette adresse, un lien de réinitialisation a été envoyé.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();

        if ($this->name() !== 'test_smtp_indisponible_repond_200_et_log_sans_jeton') {
            Mail::fake();
        }
    }

    public function test_email_existant_envoie_un_lien_sans_changer_le_mot_de_passe(): void
    {
        $utilisateur = $this->superAdmin(['email' => 'actif@itassel.test']);

        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'actif@itassel.test'])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_NEUTRE]);

        Mail::assertSent(ReinitialisationMotDePasseMail::class, function (ReinitialisationMotDePasseMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('actif@itassel.test')
                && str_contains($html, 'votre mot de passe actuel reste inchangé')
                && str_contains($html, $mail->lien)
                && str_contains($html, (string) config('itassel.jetons.reinitialisation_minutes', 60));
        });
        Mail::assertNothingQueued();
        $this->assertSame(1, JetonMotDePasse::where('type', 'reinitialisation')->count());

        $this->postJson('/api/admin/login', [
            'email' => 'actif@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertOk();

        $ligne = Journal::where('action', 'reinitialisation_mot_de_passe')->latest('id_journal')->first();
        $this->assertNotNull($ligne);
        $this->assertNull($ligne->id_utilisateur);
        $this->assertSame($utilisateur->id_utilisateur, $ligne->cible_id);
        $this->assertSame('Demande en libre-service', $ligne->detail);
        $this->assertSame('succes', $ligne->resultat);
        $this->assertStringNotContainsStringIgnoringCase('jeton', (string) $ligne->detail);
    }

    public function test_email_inconnu_repond_200_sans_mail_ni_jeton(): void
    {
        $reponse = $this->postJson('/api/admin/mot-de-passe-oublie', [
            'email' => 'absent@itassel.test',
        ]);

        $reponse->assertOk()->assertExactJson(['message' => self::MESSAGE_NEUTRE]);
        Mail::assertNothingSent();
        $this->assertSame(0, JetonMotDePasse::count());
        $this->assertFalse(Journal::where(function ($q) {
            $q->where('compte', 'like', '%absent@itassel.test%')
                ->orWhere('detail', 'like', '%absent@itassel.test%');
        })->exists());
    }

    public function test_utilisateur_inactif_ou_sans_mot_de_passe_defini_ne_recoit_pas_de_mail(): void
    {
        $this->superAdmin(['email' => 'inactif@itassel.test', 'actif' => false]);
        $this->superAdmin(['email' => 'invite@itassel.test', 'mot_de_passe_defini_le' => null]);

        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'inactif@itassel.test'])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_NEUTRE]);

        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'invite@itassel.test'])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_NEUTRE]);

        Mail::assertNothingSent();
    }

    public function test_email_en_majuscules_avec_espaces_retrouve_le_compte(): void
    {
        $this->superAdmin(['email' => 'casse@itassel.test']);

        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => '  CASSE@ITASSEL.TEST  '])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_NEUTRE]);

        Mail::assertSent(ReinitialisationMotDePasseMail::class, 1);
        $this->assertSame(1, JetonMotDePasse::where('type', 'reinitialisation')->count());
    }

    public function test_deuxieme_demande_sous_60_secondes_ne_reemet_pas_de_jeton(): void
    {
        $this->superAdmin(['email' => 'spam@itassel.test']);

        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'spam@itassel.test'])->assertOk();
        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'spam@itassel.test'])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_NEUTRE]);

        Mail::assertSent(ReinitialisationMotDePasseMail::class, 1);
        $this->assertSame(1, JetonMotDePasse::where('type', 'reinitialisation')->count());
    }

    public function test_quatrieme_demande_en_15_minutes_ne_renvoie_pas_de_mail(): void
    {
        $this->superAdmin(['email' => 'limite@itassel.test']);

        for ($i = 0; $i < 3; $i++) {
            $this->travel($i === 0 ? 0 : 61)->seconds();
            $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'limite@itassel.test'])
                ->assertOk()
                ->assertExactJson(['message' => self::MESSAGE_NEUTRE]);
        }

        Mail::assertSent(ReinitialisationMotDePasseMail::class, 3);

        $this->travel(61)->seconds();
        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'limite@itassel.test'])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_NEUTRE]);

        Mail::assertSent(ReinitialisationMotDePasseMail::class, 3);
    }

    public function test_email_mal_forme_renvoie_422(): void
    {
        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'pas-un-email'])
            ->assertStatus(422);
    }

    public function test_smtp_indisponible_repond_200_et_log_sans_jeton(): void
    {
        $this->superAdmin(['email' => 'smtp@itassel.test']);

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('SMTP indisponible'));
        Log::spy();

        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'smtp@itassel.test'])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_NEUTRE]);

        $this->assertSame(1, JetonMotDePasse::where('type', 'reinitialisation')->count());

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $contexte) {
            $serialise = json_encode($contexte);

            return $message === 'Échec envoi mail réinitialisation'
                && isset($contexte['id_utilisateur'], $contexte['erreur'])
                && ! str_contains(mb_strtolower($serialise), 'jeton')
                && ! str_contains($serialise, 'definir-mot-de-passe');
        });
    }

    public function test_parcours_complet_demande_verification_definition(): void
    {
        $utilisateur = $this->superAdmin(['email' => 'parcours@itassel.test']);

        $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'parcours@itassel.test'])
            ->assertOk();

        $jeton = $this->jetonDepuisMailEnvoye();

        $this->postJson('/api/admin/mot-de-passe/verifier-jeton', ['jeton' => $jeton])
            ->assertOk()
            ->assertJson([
                'valide' => true,
                'type'   => 'reinitialisation',
            ]);

        $this->postJson('/api/admin/mot-de-passe/definir', [
            'jeton' => $jeton,
            'mot_de_passe' => 'NouveauPass1',
            'mot_de_passe_confirmation' => 'NouveauPass1',
        ])->assertOk();

        $this->assertTrue(Hash::check('NouveauPass1', $utilisateur->fresh()->mot_de_passe));
        $this->assertNotNull($utilisateur->fresh()->mot_de_passe_defini_le);
        $this->assertSame(1, Journal::where('action', 'mot_de_passe_defini')->count());

        $this->postJson('/api/admin/login', [
            'email' => 'parcours@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertStatus(422);

        $this->postJson('/api/admin/login', [
            'email' => 'parcours@itassel.test',
            'mot_de_passe' => 'NouveauPass1',
        ])->assertOk();

        $this->travel(61)->seconds();

        $this->postJson('/api/admin/mot-de-passe/definir', [
            'jeton' => $jeton,
            'mot_de_passe' => 'AutrePass12',
            'mot_de_passe_confirmation' => 'AutrePass12',
        ])->assertStatus(422)->assertJsonFragment(['Lien invalide ou expiré.']);

        $this->postJson('/api/admin/mot-de-passe/verifier-jeton', ['jeton' => $jeton])
            ->assertOk()
            ->assertJson(['valide' => false]);
    }

    public function test_reinitialisation_super_admin_bloque_toujours_le_compte(): void
    {
        $acteur = $this->superAdmin();
        $cible = $this->adminService(null, ['email' => 'bloque@itassel.test']);
        $token = $cible->createToken('t')->plainTextToken;

        $this->connecter($acteur)
            ->postJson("/api/admin/utilisateurs/{$cible->id_utilisateur}/reinitialiser-mot-de-passe")
            ->assertOk();

        $this->assertSame(0, $cible->fresh()->tokens()->count());
        $this->assertFalse(Hash::check('Itassel2026!', $cible->fresh()->mot_de_passe));
        Mail::assertSent(ReinitialisationMotDePasseMail::class);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($token)->getJson('/api/admin/me')->assertStatus(401);

        $this->postJson('/api/admin/login', [
            'email' => 'bloque@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertStatus(422);
    }

    private function jetonDepuisMailEnvoye(): string
    {
        $mail = Mail::sent(ReinitialisationMotDePasseMail::class)[0];
        parse_str((string) parse_url($mail->lien, PHP_URL_QUERY), $query);

        $this->assertNotEmpty($query['jeton'] ?? null);

        return $query['jeton'];
    }
}

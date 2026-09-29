<?php

namespace Tests\Feature;

use App\Mail\DemandeComplementMail;
use App\Models\Complement;
use App\Models\Historique;
use App\Models\PieceJointe;
use App\Models\Statut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ComplementCitoyenTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
    }

    public function test_nouvelle_vers_information_demandee_avec_question(): void
    {
        $admin = $this->adminService();
        $this->connecter($admin);
        $doleance = $this->doleance([
            'statut'     => Statut::NOUVELLE,
            'id_service' => $admin->id_service,
        ]);
        $idNouvelle = Statut::parCode(Statut::NOUVELLE)->id_statut;
        $idInfo = Statut::parCode(Statut::INFORMATION_DEMANDEE)->id_statut;

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => $idInfo,
            'question'  => 'Pouvez-vous préciser le lieu ?',
        ])->assertOk();

        $doleance->refresh();
        $this->assertSame(Statut::INFORMATION_DEMANDEE, $doleance->statut->code);
        $this->assertSame($admin->id_utilisateur, $doleance->id_responsable);

        $complement = Complement::where('id_doleance', $doleance->id_doleance)->first();
        $this->assertNotNull($complement);
        $this->assertSame('en_attente', $complement->etat);
        $this->assertSame('Pouvez-vous préciser le lieu ?', $complement->question);

        $evenement = Historique::where('id_doleance', $doleance->id_doleance)
            ->where('type_evenement', 'complement_demande')
            ->first();
        $this->assertNotNull($evenement);
        $this->assertSame($idNouvelle, $evenement->id_statut_avant);
        $this->assertSame($idInfo, $evenement->id_statut_apres);
    }

    public function test_demander_complement_accepte_un_dossier_nouvelle(): void
    {
        $admin = $this->adminService();
        $this->connecter($admin);
        $doleance = $this->doleance([
            'statut'     => Statut::NOUVELLE,
            'id_service' => $admin->id_service,
        ]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/complements", [
            'question' => 'Merci de joindre une copie du document.',
        ])->assertCreated();

        $doleance->refresh();
        $this->assertSame(Statut::INFORMATION_DEMANDEE, $doleance->statut->code);
        $this->assertSame($admin->id_utilisateur, $doleance->id_responsable);
        $this->assertSame('en_attente', Complement::where('id_doleance', $doleance->id_doleance)->value('etat'));
    }

    public function test_en_cours_vers_information_demandee(): void
    {
        $admin = $this->adminService();
        $this->connecter($admin);
        $doleance = $this->doleance([
            'statut'         => Statut::EN_COURS,
            'id_service'     => $admin->id_service,
            'id_responsable' => $admin->id_utilisateur,
        ]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::INFORMATION_DEMANDEE)->id_statut,
            'question'  => 'Quel est le numéro de licence ?',
        ])->assertOk();

        $this->assertSame(Statut::INFORMATION_DEMANDEE, $doleance->fresh()->statut->code);
        $this->assertSame('en_attente', Complement::where('id_doleance', $doleance->id_doleance)->value('etat'));
    }

    public function test_question_absente_renvoie_422(): void
    {
        $admin = $this->adminService();
        $this->connecter($admin);
        $doleance = $this->doleance([
            'statut'     => Statut::NOUVELLE,
            'id_service' => $admin->id_service,
        ]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::INFORMATION_DEMANDEE)->id_statut,
        ])->assertStatus(422);
    }

    public function test_suivi_dossier_expose_le_complement_en_attente(): void
    {
        $doleance = $this->doleance(['statut' => Statut::INFORMATION_DEMANDEE]);
        Complement::factory()->create([
            'id_doleance'         => $doleance->id_doleance,
            'etat'                => 'en_attente',
            'question'            => 'Merci de préciser.',
            'piece_exigee'        => true,
            'description_piece'   => 'Scan de la pièce d\'identité',
            'motif_annulation'    => 'ne doit pas apparaître',
        ]);

        $reponse = $this->avecSessionSuivi($doleance)
            ->getJson('/api/suivi/dossier')
            ->assertOk();

        $complements = $reponse->json('complements');
        $this->assertCount(1, $complements);
        $this->assertSame('Merci de préciser.', $complements[0]['question']);
        $this->assertTrue($complements[0]['piece_exigee']);
        $this->assertSame('Scan de la pièce d\'identité', $complements[0]['description_piece']);
        $this->assertArrayNotHasKey('motif_annulation', $complements[0]);
    }

    public function test_reponse_message_seul_quand_piece_non_exigee(): void
    {
        [$doleance, $complement] = $this->dossierAvecComplementEnAttente(pieceExigee: false);

        $this->avecSessionSuivi($doleance)
            ->postJson('/api/suivi/repondre-complement', [
                'message' => 'Voici la précision demandée.',
            ])
            ->assertOk();

        $this->assertSame('recu', $complement->fresh()->etat);
        $this->assertSame(Statut::EN_COURS, $doleance->fresh()->statut->code);

        $evenement = Historique::where('id_doleance', $doleance->id_doleance)
            ->where('type_evenement', 'complement_recu')
            ->first();
        $this->assertNotNull($evenement);
        $this->assertTrue($evenement->visible_demandeur);
    }

    public function test_piece_exigee_absente_renvoie_422(): void
    {
        [$doleance] = $this->dossierAvecComplementEnAttente(pieceExigee: true);

        $this->avecSessionSuivi($doleance)
            ->postJson('/api/suivi/repondre-complement', [
                'message' => 'Sans fichier.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['piece_jointe']);
    }

    public function test_reponse_avec_image_enregistre_sans_exif(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Extension GD requise pour le ré-encodage EXIF.');
        }

        [$doleance, $complement] = $this->dossierAvecComplementEnAttente(pieceExigee: true);

        $cheminExif = sys_get_temp_dir().DIRECTORY_SEPARATOR.'itassel-exif-'.uniqid('', true).'.jpg';
        $jpegExif = $this->jpegAvecExif();
        file_put_contents($cheminExif, $jpegExif);
        $this->assertStringContainsString('Exif', $jpegExif);

        $this->avecSessionSuivi($doleance)
            ->post('/api/suivi/repondre-complement', [
                'message'      => 'Voici la photo.',
                'piece_jointe' => new UploadedFile($cheminExif, 'photo.jpg', 'image/jpeg', null, true),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        @unlink($cheminExif);

        $piece = PieceJointe::where('id_complement', $complement->id_complement)->first();
        $this->assertNotNull($piece);
        $this->assertSame('COMPLEMENT', $piece->origine);
        $this->assertSame($complement->id_complement, $piece->id_complement);

        $contenu = Storage::disk('local')->get($piece->chemin);
        $this->assertStringNotContainsString('Exif', $contenu);
        $this->assertStringNotContainsString('48.8566', $contenu);
    }

    public function test_fichier_zip_ou_trop_volumineux_renvoie_422(): void
    {
        [$doleance] = $this->dossierAvecComplementEnAttente(pieceExigee: false);

        $this->avecSessionSuivi($doleance)
            ->post('/api/suivi/repondre-complement', [
                'message'      => 'Archive.',
                'piece_jointe' => UploadedFile::fake()->create('archive.zip', 100, 'application/zip'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->avecSessionSuivi($doleance)
            ->post('/api/suivi/repondre-complement', [
                'message'      => 'Trop gros.',
                'piece_jointe' => UploadedFile::fake()->create('gros.pdf', 5121, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_deuxieme_reponse_au_meme_complement_renvoie_404(): void
    {
        [$doleance] = $this->dossierAvecComplementEnAttente(pieceExigee: false);
        $headers = ['X-Suivi-Token' => $this->jetonSuivi($doleance)];

        $this->postJson('/api/suivi/repondre-complement', [
            'message' => 'Première réponse.',
        ], $headers)->assertOk();

        $this->postJson('/api/suivi/repondre-complement', [
            'message' => 'Deuxième tentative.',
        ], $headers)->assertStatus(404);
    }

    public function test_sans_en_tete_suivi_token_renvoie_401(): void
    {
        $this->dossierAvecComplementEnAttente(pieceExigee: false);

        $this->postJson('/api/suivi/repondre-complement', [
            'message' => 'Sans session.',
        ])->assertStatus(401);
    }

    public function test_complement_annule_refuse_la_reponse(): void
    {
        $admin = $this->adminService();
        [$doleance, $complement] = $this->dossierAvecComplementEnAttente(
            pieceExigee: false,
            admin: $admin,
        );

        $this->connecter($admin)
            ->postJson("/api/admin/complements/{$complement->id_complement}/annuler", [
                'motif' => 'Plus nécessaire.',
            ])
            ->assertOk();

        $this->avecSessionSuivi($doleance)
            ->postJson('/api/suivi/repondre-complement', [
                'message' => 'Trop tard.',
            ])
            ->assertStatus(404);
    }

    public function test_le_lien_email_contient_suivre_avec_reference(): void
    {
        $admin = $this->adminService();
        $this->connecter($admin);
        $doleance = $this->doleance([
            'statut'     => Statut::NOUVELLE,
            'id_service' => $admin->id_service,
        ]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::INFORMATION_DEMANDEE)->id_statut,
            'question'  => 'Une précision s\'il vous plaît.',
        ])->assertOk();

        Mail::assertSent(DemandeComplementMail::class, function (DemandeComplementMail $mail) use ($doleance) {
            return str_contains($mail->lienSuivi, '/suivre?reference=')
                && str_contains($mail->lienSuivi, urlencode($doleance->reference));
        });
    }

    /**
     * @return array{0: \App\Models\Doleance, 1: Complement}
     */
    private function dossierAvecComplementEnAttente(
        bool $pieceExigee,
        ?\App\Models\Utilisateur $admin = null,
    ): array {
        $admin ??= $this->adminService();
        $doleance = $this->doleance([
            'statut'         => Statut::INFORMATION_DEMANDEE,
            'id_service'     => $admin->id_service,
            'id_responsable' => $admin->id_utilisateur,
        ]);
        $complement = Complement::factory()->create([
            'id_doleance'       => $doleance->id_doleance,
            'id_auteur'         => $admin->id_utilisateur,
            'etat'              => 'en_attente',
            'piece_exigee'      => $pieceExigee,
            'description_piece' => $pieceExigee ? 'Justificatif' : null,
        ]);

        return [$doleance, $complement];
    }

    private function jetonSuivi(\App\Models\Doleance $doleance): string
    {
        $jeton = Str::random(48);
        Cache::put("suivi:session:{$jeton}", $doleance->id_doleance, 600);

        return $jeton;
    }

    private function avecSessionSuivi(\App\Models\Doleance $doleance): static
    {
        return $this->withHeader('X-Suivi-Token', $this->jetonSuivi($doleance));
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

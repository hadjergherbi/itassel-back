<?php

namespace Tests\Feature;

use App\Mail\NotificationInterneMail;
use App\Models\Journal;
use App\Models\NoteInterne;
use App\Models\NotificationApp;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class NotesInternesTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_mentionnables_sont_filtres_sans_email(): void
    {
        $sport = Service::where('nom_service', 'Sport')->first() ?? Service::first();
        $autre = Service::where('id_service', '!=', $sport->id_service)->first();
        $doleance = $this->doleance(['id_service' => $sport->id_service]);

        $auteur = $this->adminService($sport, ['prenom' => 'Auteur', 'nom' => 'Notes']);
        $collegue = $this->adminService($sport, ['prenom' => 'Amine', 'nom' => 'Kaci']);
        $inactif = $this->adminService($sport, ['actif' => false, 'prenom' => 'Inactif']);
        $horsService = $this->adminService($autre, ['prenom' => 'Hors']);
        $super = $this->superAdmin(['prenom' => 'Sara', 'nom' => 'Admin']);

        $liste = $this->connecter($auteur)
            ->getJson("/api/admin/doleances/{$doleance->reference}/mentionnables")
            ->assertOk()
            ->json();

        $ids = collect($liste)->pluck('id_utilisateur')->all();
        $this->assertContains($collegue->id_utilisateur, $ids);
        $this->assertContains($super->id_utilisateur, $ids);
        $this->assertNotContains($auteur->id_utilisateur, $ids);
        $this->assertNotContains($inactif->id_utilisateur, $ids);
        $this->assertNotContains($horsService->id_utilisateur, $ids);

        foreach ($liste as $ligne) {
            $this->assertArrayNotHasKey('email', $ligne);
            $this->assertArrayHasKey('initiales', $ligne);
            $this->assertArrayHasKey('libelle_role', $ligne);
            $this->assertArrayHasKey('service', $ligne);
        }

        $filtre = $this->getJson("/api/admin/doleances/{$doleance->reference}/mentionnables?q=ami")
            ->assertOk()
            ->json();
        $this->assertTrue(collect($filtre)->contains('id_utilisateur', $collegue->id_utilisateur));
    }

    public function test_mentionnables_d_un_autre_service_renvoie_404(): void
    {
        $sport = Service::where('nom_service', 'Sport')->first() ?? Service::first();
        $autre = Service::where('id_service', '!=', $sport->id_service)->first();
        $doleance = $this->doleance(['id_service' => $autre->id_service]);

        $this->connecter($this->adminService($sport))
            ->getJson("/api/admin/doleances/{$doleance->reference}/mentionnables")
            ->assertStatus(404);
    }

    public function test_creation_avec_mention_valide_et_invalide(): void
    {
        $service = Service::first();
        $auteur = $this->adminService($service);
        $mentionne = $this->adminService($service);
        $invalide = $this->adminService(Service::where('id_service', '!=', $service->id_service)->first());
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($auteur)
            ->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
                'contenu'  => 'À voir avec @Amine pour le dossier.',
                'mentions' => [$mentionne->id_utilisateur],
                'etiquette' => 'a_verifier',
            ])
            ->assertCreated()
            ->assertJsonPath('note.mentions.0.id_utilisateur', $mentionne->id_utilisateur)
            ->assertJsonPath('note.est_auteur', true);

        $this->assertTrue(DB::table('note_mentions')->where('id_utilisateur', $mentionne->id_utilisateur)->exists());
        $this->assertTrue(NotificationApp::where('id_utilisateur', $mentionne->id_utilisateur)
            ->where('evenement', 'mention_note')->exists());
        $this->assertSame(1, Journal::where('action', 'mention_note')->count());

        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
            'contenu'  => 'Mention interdite.',
            'mentions' => [$invalide->id_utilisateur],
        ])->assertStatus(422)->assertJsonFragment([
            'Cette personne ne peut pas être mentionnée sur ce dossier.',
        ]);
    }

    public function test_notifier_email_envoie_un_mail_et_cinq_mentions_max(): void
    {
        $service = Service::first();
        $auteur = $this->adminService($service);
        $mentionne = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($auteur)
            ->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
                'contenu'        => 'Merci de relire cette note interne.',
                'mentions'       => [$mentionne->id_utilisateur],
                'notifier_email' => true,
            ])
            ->assertCreated();

        Mail::assertSent(NotificationInterneMail::class, function (NotificationInterneMail $mail) use ($mentionne, $doleance) {
            return $mail->hasTo($mentionne->email)
                && str_contains($mail->lienDossier, '/admin/doleances/'.$doleance->reference);
        });

        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = $this->adminService($service)->id_utilisateur;
        }

        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
            'contenu'  => 'Trop de mentions.',
            'mentions' => $ids,
        ])->assertStatus(422);
    }

    public function test_modification_delai_et_droits(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));
        $service = Service::first();
        $auteur = $this->adminService($service);
        $autre = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($auteur);
        $id = $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
            'contenu' => 'Première version.',
        ])->assertCreated()->json('note.id_note');

        $this->putJson("/api/admin/doleances/{$doleance->reference}/notes/{$id}", [
            'contenu' => 'Version corrigée.',
        ])->assertOk()->assertJsonPath('note.contenu', 'Version corrigée.');

        $this->assertNotNull(NoteInterne::find($id)->modifiee_le);

        $this->connecter($autre)
            ->putJson("/api/admin/doleances/{$doleance->reference}/notes/{$id}", [
                'contenu' => 'Intrusion.',
            ])
            ->assertStatus(403)
            ->assertJson(['message' => 'Vous ne pouvez modifier que vos propres notes.']);

        Carbon::setTestNow(Carbon::parse('2026-09-24 12:16:00'));
        $this->connecter($auteur)
            ->putJson("/api/admin/doleances/{$doleance->reference}/notes/{$id}", [
                'contenu' => 'Trop tard.',
            ])
            ->assertStatus(409)
            ->assertJson(['message' => 'Le délai de modification de 15 minutes est dépassé.']);
    }

    public function test_nouvelle_mention_est_notifiee_ancienne_non(): void
    {
        $service = Service::first();
        $auteur = $this->adminService($service);
        $premier = $this->adminService($service);
        $second = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($auteur);
        $id = $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
            'contenu'  => 'Pour le premier.',
            'mentions' => [$premier->id_utilisateur],
        ])->assertCreated()->json('note.id_note');

        $this->assertSame(1, NotificationApp::where('id_utilisateur', $premier->id_utilisateur)->count());

        $this->putJson("/api/admin/doleances/{$doleance->reference}/notes/{$id}", [
            'contenu'  => 'Pour les deux.',
            'mentions' => [$premier->id_utilisateur, $second->id_utilisateur],
        ])->assertOk();

        $this->assertSame(1, NotificationApp::where('id_utilisateur', $premier->id_utilisateur)->count());
        $this->assertSame(1, NotificationApp::where('id_utilisateur', $second->id_utilisateur)->count());
    }

    public function test_aucune_route_de_suppression(): void
    {
        $service = Service::first();
        $auteur = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($auteur);
        $id = $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
            'contenu' => 'À conserver.',
        ])->json('note.id_note');

        $this->deleteJson("/api/admin/doleances/{$doleance->reference}/notes/{$id}")
            ->assertStatus(405);

        $this->assertTrue(NoteInterne::whereKey($id)->exists());
    }

    public function test_epinglage_bascule_et_limite(): void
    {
        $service = Service::first();
        $agent = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service]);
        $this->connecter($agent);

        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            $ids[] = $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
                'contenu' => 'Note '.$i,
            ])->json('note.id_note');
        }

        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes/{$ids[0]}/epingler")
            ->assertOk()
            ->assertJsonPath('note.epinglee', true);
        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes/{$ids[0]}/epingler")
            ->assertOk()
            ->assertJsonPath('note.epinglee', false);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes/{$ids[0]}/epingler")->assertOk();
        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes/{$ids[1]}/epingler")->assertOk();
        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes/{$ids[2]}/epingler")->assertOk();
        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes/{$ids[3]}/epingler")
            ->assertStatus(422)
            ->assertJsonFragment(['3 notes au maximum peuvent être épinglées.']);
    }

    public function test_ordre_des_notes_et_champs_auteur(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00'));
        $service = Service::first();
        $auteur = $this->adminService($service);
        $lecteur = $this->superAdmin();
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($auteur);
        $ancienne = $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
            'contenu' => 'Ancienne',
        ])->json('note.id_note');

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:05:00'));
        $recente = $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
            'contenu' => 'Récente',
        ])->json('note.id_note');

        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes/{$recente}/epingler")->assertOk();

        $notes = $this->connecter($lecteur)
            ->getJson("/api/admin/doleances/{$doleance->reference}")
            ->assertOk()
            ->json('notes_internes');

        $this->assertSame($recente, $notes[0]['id_note']);
        $this->assertTrue($notes[0]['epinglee']);
        $this->assertSame($ancienne, $notes[1]['id_note']);
        $this->assertFalse($notes[1]['est_auteur']);
        $this->assertNull($notes[1]['modifiable_jusqu_a']);
        $this->assertArrayHasKey('created_at', $notes[0]);
        $this->assertArrayHasKey('date_creation', $notes[0]);

        $sesNotes = $this->connecter($auteur)
            ->getJson("/api/admin/doleances/{$doleance->reference}")
            ->json('notes_internes');
        $this->assertTrue($sesNotes[1]['est_auteur']);
        $this->assertNotNull($sesNotes[1]['modifiable_jusqu_a']);
    }

    public function test_la_liste_expose_nb_notes(): void
    {
        $service = Service::first();
        $auteur = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($auteur)
            ->postJson("/api/admin/doleances/{$doleance->reference}/notes", ['contenu' => 'Une'])
            ->assertCreated();
        $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", ['contenu' => 'Deux'])
            ->assertCreated();

        $ligne = collect($this->getJson('/api/admin/doleances')->assertOk()->json('doleances.data'))
            ->firstWhere('reference', $doleance->reference);

        $this->assertNotNull($ligne);
        $this->assertSame(2, $ligne['nb_notes']);
    }
}

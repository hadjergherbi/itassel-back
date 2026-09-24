<?php

namespace Tests\Feature;

use App\Models\NotificationApp;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class MentionNotificationTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_une_mention_cree_une_notification_liee_a_la_note(): void
    {
        $service = Service::first();
        $auteur = $this->adminService($service, ['prenom' => 'Lina', 'nom' => 'Benali']);
        $mentionne = $this->adminService($service, ['prenom' => 'Amine', 'nom' => 'Kaci']);
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $idNote = $this->connecter($auteur)
            ->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
                'contenu'  => 'Merci de relire @Amine Kaci.',
                'mentions' => [$mentionne->id_utilisateur],
            ])
            ->assertCreated()
            ->json('note.id_note');

        $notif = NotificationApp::where('id_utilisateur', $mentionne->id_utilisateur)
            ->where('evenement', 'mention_note')
            ->first();

        $this->assertNotNull($notif);
        $this->assertSame($doleance->id_doleance, $notif->id_doleance);
        $this->assertSame($idNote, $notif->id_note);
        $this->assertSame(
            0,
            NotificationApp::where('id_utilisateur', $auteur->id_utilisateur)->count()
        );
    }

    public function test_une_modification_ne_notifie_que_la_nouvelle_mention(): void
    {
        $service = Service::first();
        $auteur = $this->adminService($service);
        $premier = $this->adminService($service);
        $second = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($auteur);
        $idNote = $this->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
            'contenu'  => 'Pour le premier.',
            'mentions' => [$premier->id_utilisateur],
        ])->assertCreated()->json('note.id_note');

        $this->putJson("/api/admin/doleances/{$doleance->reference}/notes/{$idNote}", [
            'contenu'  => 'Pour les deux.',
            'mentions' => [$premier->id_utilisateur, $second->id_utilisateur],
        ])->assertOk();

        $this->assertSame(1, NotificationApp::where('id_utilisateur', $premier->id_utilisateur)->count());
        $this->assertSame(1, NotificationApp::where('id_utilisateur', $second->id_utilisateur)->count());

        $nouvelle = NotificationApp::where('id_utilisateur', $second->id_utilisateur)->first();
        $this->assertSame($idNote, $nouvelle->id_note);
        $this->assertSame($doleance->id_doleance, $nouvelle->id_doleance);
    }

    public function test_la_liste_et_le_compteur_exposent_la_note_et_l_auteur(): void
    {
        $service = Service::first();
        $auteur = $this->adminService($service, ['prenom' => 'Lina', 'nom' => 'Benali']);
        $mentionne = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service]);

        $idNote = $this->connecter($auteur)
            ->postJson("/api/admin/doleances/{$doleance->reference}/notes", [
                'contenu'  => 'Pouvez-vous vérifier ce point ?',
                'mentions' => [$mentionne->id_utilisateur],
            ])
            ->assertCreated()
            ->json('note.id_note');

        $this->connecter($mentionne)
            ->getJson('/api/admin/mes-notifications/compteur')
            ->assertOk()
            ->assertJson(['non_lues' => 1]);

        $this->getJson('/api/admin/mes-notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id_note', $idNote)
            ->assertJsonPath('data.0.evenement', 'mention_note')
            ->assertJsonPath('data.0.doleance.reference', $doleance->reference)
            ->assertJsonPath('data.0.auteur.prenom', 'Lina')
            ->assertJsonPath('data.0.auteur.nom', 'Benali')
            ->assertJsonPath('data.0.titre', 'Lina Benali vous a mentionné')
            ->assertJsonPath('data.0.message', 'Pouvez-vous vérifier ce point ?');
    }
}

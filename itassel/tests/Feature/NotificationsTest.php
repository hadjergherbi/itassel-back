<?php

namespace Tests\Feature;

use App\Models\NotificationApp;
use App\Models\ParametreNotification;
use App\Models\Service;
use App\Models\Statut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
    }

    public function test_depot_cree_notifications_app_et_exclut_inactifs_et_hors_perimetre(): void
    {
        $service = Service::first();
        $autre = Service::where('id_service', '!=', $service->id_service)->first();
        $responsable = $this->adminService($service);
        $service->update(['id_responsable' => $responsable->id_utilisateur]);
        $inactif = $this->adminService($service, ['actif' => false, 'email' => 'inactif@itassel.test']);
        $horsPerimetre = $this->adminService($autre);

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'id_service' => $service->id_service,
        ]))->assertCreated();

        $this->assertGreaterThan(0, NotificationApp::count());
        $this->assertFalse(NotificationApp::where('id_utilisateur', $inactif->id_utilisateur)->exists());
        $this->assertFalse(NotificationApp::where('id_utilisateur', $horsPerimetre->id_utilisateur)->exists());
        $this->assertTrue(NotificationApp::where('id_utilisateur', $responsable->id_utilisateur)->exists());
    }

    public function test_l_acteur_est_exclu_et_les_parametres_sont_respectes(): void
    {
        $admin = $this->superAdmin();
        $doleance = $this->doleance(['id_responsable' => $admin->id_utilisateur, 'statut' => Statut::EN_COURS]);

        ParametreNotification::where('evenement', 'changement_statut')
            ->where('destinataire', 'responsable')
            ->update(['canal_app' => false, 'canal_email' => false]);

        $this->connecter($admin)->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::RESOLUE)->id_statut,
            'message' => 'Traité.',
        ])->assertOk();

        $this->assertFalse(NotificationApp::where('id_utilisateur', $admin->id_utilisateur)->exists());
    }

    public function test_ligne_non_modifiable_refusee(): void
    {
        $this->connecter($this->superAdmin())
            ->putJson('/api/admin/parametres/notifications', [[
                'evenement' => 'doleance_deposee',
                'destinataire' => 'demandeur',
                'canal_email' => false,
                'canal_app' => false,
            ]])
            ->assertStatus(422);
    }

    public function test_lecture_des_notifications_limitee_au_proprietaire(): void
    {
        $a = $this->superAdmin(['email' => 'a@itassel.test']);
        $b = $this->superAdmin(['email' => 'b@itassel.test']);
        $doleance = $this->doleance();

        $notif = NotificationApp::create([
            'id_utilisateur' => $a->id_utilisateur,
            'evenement' => 'doleance_deposee',
            'titre' => 'Test',
            'message' => 'Message',
            'id_doleance' => $doleance->id_doleance,
        ]);

        $this->connecter($b)
            ->postJson("/api/admin/mes-notifications/{$notif->id_notification_app}/lire")
            ->assertStatus(404);

        $this->connecter($a)
            ->getJson('/api/admin/mes-notifications?non_lues=1')
            ->assertOk()
            ->assertJsonPath('data.0.id_notification_app', $notif->id_notification_app);
    }
}

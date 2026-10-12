<?php

namespace Tests\Feature;

use App\Models\NotificationApp;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class NotificationsReaffectationsMultiServicesTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
    }

    public function test_le_depot_notifie_les_admins_de_tous_les_services_concernes(): void
    {
        $jeunesse = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $sport = $this->serviceUsuel();
        $rh = Service::where('nom_service', 'Ressources humaines')->firstOrFail();

        $principal = $this->adminService($jeunesse, ['email' => 'principal.jeunesse@itassel.test']);
        $aussi = $this->adminService($sport, ['email' => 'aussi.jeunesse@itassel.test']);
        $aussi->services()->syncWithoutDetaching([$jeunesse->id_service]);
        $hors = $this->adminService($rh, ['email' => 'hors.rh@itassel.test']);

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'id_service' => $jeunesse->id_service,
        ]))->assertCreated();

        $this->assertTrue(NotificationApp::where('id_utilisateur', $principal->id_utilisateur)->exists());
        $this->assertTrue(NotificationApp::where('id_utilisateur', $aussi->id_utilisateur)->exists());
        $this->assertFalse(NotificationApp::where('id_utilisateur', $hors->id_utilisateur)->exists());
    }

    public function test_une_mention_exige_que_ladmin_gere_le_service_du_dossier(): void
    {
        $jeunesse = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $sport = $this->serviceUsuel();
        $rh = Service::where('nom_service', 'Ressources humaines')->firstOrFail();
        $dossier = $this->doleance(['id_service' => $jeunesse->id_service]);

        $auteur = $this->adminService($jeunesse, ['email' => 'auteur.mention@itassel.test']);
        $concerne = $this->adminService($sport, ['prenom' => 'Nabil', 'email' => 'nabil.mention@itassel.test']);
        $concerne->services()->syncWithoutDetaching([$jeunesse->id_service]);
        $hors = $this->adminService($rh, ['prenom' => 'Hors', 'email' => 'hors.mention@itassel.test']);

        $ids = collect($this->connecter($auteur)
            ->getJson("/api/admin/doleances/{$dossier->reference}/mentionnables")
            ->assertOk()
            ->json())->pluck('id_utilisateur');

        $this->assertTrue($ids->contains($concerne->id_utilisateur));
        $this->assertFalse($ids->contains($hors->id_utilisateur));
    }

    public function test_reaffectation_depuis_un_service_du_pivot_puis_perte_de_visibilite(): void
    {
        $sport = $this->serviceUsuel();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $rh = Service::where('nom_service', 'Ressources humaines')->firstOrFail();

        $demandeur = $this->adminService($sport, ['email' => 'demandeur.multi@itassel.test']);
        $demandeur->services()->syncWithoutDetaching([$jeunesse->id_service]);
        $demandeur->oublierIdsServices();
        $destinataire = $this->adminService($rh, ['email' => 'destinataire.rh@itassel.test']);
        $dossier = $this->doleance(['id_service' => $jeunesse->id_service]);

        $this->connecter($demandeur)
            ->postJson("/api/admin/doleances/{$dossier->reference}/reaffectation", [
                'motif' => 'Mauvais domaine',
                'id_service_propose' => $jeunesse->id_service,
            ])->assertStatus(422);

        $cree = $this->postJson("/api/admin/doleances/{$dossier->reference}/reaffectation", [
            'motif' => 'Relève de ce domaine',
            'id_service_propose' => $rh->id_service,
        ])->assertCreated();

        $id = $cree->json('reaffectation.id_reaffectation');

        $this->connecter($this->adminService($sport, ['email' => 'intrus.sport@itassel.test']))
            ->postJson("/api/admin/reaffectations/{$id}/accepter", [
                'id_service_destination' => $rh->id_service,
            ])->assertForbidden();

        $this->connecter($destinataire)
            ->postJson("/api/admin/reaffectations/{$id}/accepter", [
                'id_service_destination' => $rh->id_service,
            ])->assertOk();

        $demandeur->oublierIdsServices();
        $this->connecter($demandeur)
            ->getJson("/api/admin/doleances/{$dossier->reference}")
            ->assertForbidden()
            ->assertJsonPath('code', 'dossier_reaffecte');
    }
}

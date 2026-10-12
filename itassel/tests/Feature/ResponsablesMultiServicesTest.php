<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ResponsablesMultiServicesTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        $this->connecter($this->superAdmin());
    }

    public function test_un_admin_peut_etre_responsable_de_plusieurs_services_de_son_pivot(): void
    {
        $sport = $this->serviceUsuel();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $admin = $this->adminService($sport, ['email' => 'multi.resp@itassel.test']);
        $admin->services()->syncWithoutDetaching([$jeunesse->id_service]);
        $admin->oublierIdsServices();

        $this->putJson("/api/admin/services/{$jeunesse->id_service}/responsable", [
            'id_responsable' => $admin->id_utilisateur,
        ])->assertOk();

        $this->putJson("/api/admin/services/{$sport->id_service}/responsable", [
            'id_responsable' => $admin->id_utilisateur,
        ])->assertOk();

        $this->assertSame($admin->id_utilisateur, $jeunesse->fresh()->id_responsable);
        $this->assertSame($admin->id_utilisateur, $sport->fresh()->id_responsable);
    }

    public function test_la_designation_refuse_un_admin_qui_ne_gere_pas_le_service(): void
    {
        $jeunesse = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $rh = Service::where('nom_service', 'Ressources humaines')->firstOrFail();
        $hors = $this->adminService($rh, ['email' => 'hors.jeunesse@itassel.test']);

        $this->putJson("/api/admin/services/{$jeunesse->id_service}/responsable", [
            'id_responsable' => $hors->id_utilisateur,
        ])->assertStatus(422)->assertJsonValidationErrors('id_responsable');

        $this->assertNotSame($hors->id_utilisateur, $jeunesse->fresh()->id_responsable);
    }

    public function test_desactiver_exige_un_remplacant_pour_chaque_service_sans_autre_admin(): void
    {
        $sport = $this->serviceUsuel();
        $isole = Service::factory()->create(['nom_service' => 'Domaine isolé']);
        $admin = $this->adminService($sport, ['email' => 'couvre.deux@itassel.test']);
        $admin->services()->syncWithoutDetaching([$isole->id_service]);
        $admin->oublierIdsServices();

        $this->postJson("/api/admin/utilisateurs/{$admin->id_utilisateur}/desactiver")
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_remplacant');

        $this->assertTrue($admin->fresh()->actif);

        $remplacant = $this->adminService($sport, ['email' => 'reprend.isole@itassel.test']);
        $remplacant->services()->syncWithoutDetaching([$isole->id_service]);
        $remplacant->oublierIdsServices();

        $this->postJson("/api/admin/utilisateurs/{$admin->id_utilisateur}/desactiver", [
            'id_remplacant' => $remplacant->id_utilisateur,
        ])->assertOk();

        $this->assertFalse($admin->fresh()->actif);
    }
}

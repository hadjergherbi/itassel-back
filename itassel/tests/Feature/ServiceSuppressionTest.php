<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\Permission;
use App\Models\Reaffectation;
use App\Models\Role;
use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ServiceSuppressionTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_le_super_admin_supprime_un_service_vide(): void
    {
        $service = Service::factory()->create(['nom_service' => 'Service vide']);

        $this->connecter($this->superAdmin())
            ->deleteJson("/api/admin/services/{$service->id_service}")
            ->assertOk()
            ->assertJson(['message' => 'Service supprimé.']);

        $this->assertNull(Service::find($service->id_service));
        $this->assertTrue(
            Journal::where('action', 'suppression_service')
                ->where('detail', 'Service vide')
                ->exists()
        );
    }

    public function test_un_admin_de_service_ne_peut_pas_supprimer(): void
    {
        $service = Service::factory()->create(['nom_service' => 'Service protégé']);
        $admin = $this->adminService();

        $permission = Permission::where('code', 'services.gerer')->first();
        $role = Role::where('code', 'admin_service')->first();
        DB::table('role_permission')->updateOrInsert([
            'id_role'       => $role->id_role,
            'id_permission' => $permission->id_permission,
        ]);
        Utilisateur::viderCachePermissions('admin_service');

        $this->connecter($admin)
            ->deleteJson("/api/admin/services/{$service->id_service}")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Seul le Super administrateur peut supprimer un service.',
                'code'    => 'non_autorise',
            ]);

        $this->assertNotNull(Service::find($service->id_service));
    }

    public function test_un_service_avec_doleance_est_conserve(): void
    {
        $service = Service::factory()->create(['nom_service' => 'Avec doléance']);
        $this->doleance(['id_service' => $service->id_service]);

        $this->connecter($this->superAdmin())
            ->deleteJson("/api/admin/services/{$service->id_service}")
            ->assertStatus(409)
            ->assertJson([
                'code'    => 'service_doleances',
                'message' => 'Ce service contient encore des doléances : il ne peut pas être supprimé.',
            ]);

        $this->assertNotNull(Service::find($service->id_service));
    }

    public function test_un_service_avec_utilisateur_actif_est_conserve(): void
    {
        $service = Service::factory()->create(['nom_service' => 'Avec agent']);
        $this->adminService($service);

        $this->connecter($this->superAdmin())
            ->deleteJson("/api/admin/services/{$service->id_service}")
            ->assertStatus(409)
            ->assertJson([
                'code'    => 'service_utilisateurs',
                'message' => 'Des utilisateurs sont encore rattachés à ce service : réaffectez-les ou supprimez-les d\'abord.',
            ]);

        $this->assertNotNull(Service::find($service->id_service));
    }

    public function test_un_utilisateur_supprime_est_detache_puis_le_service_disparait(): void
    {
        $service = Service::factory()->create(['nom_service' => 'Ancien rattachement']);
        $agent = $this->adminService($service);
        $agent->delete();

        $this->connecter($this->superAdmin())
            ->deleteJson("/api/admin/services/{$service->id_service}")
            ->assertOk();

        $this->assertNull(Service::find($service->id_service));
        $this->assertNull(Utilisateur::withTrashed()->find($agent->id_utilisateur)->id_service);
    }

    public function test_un_service_cite_dans_une_reaffectation_est_conserve(): void
    {
        $cible = Service::factory()->create(['nom_service' => 'Cité en réaffectation']);
        $autre = Service::factory()->create(['nom_service' => 'Service d\'origine']);
        $doleance = $this->doleance(['id_service' => $autre->id_service]);
        $demandeur = $this->adminService($autre);

        Reaffectation::create([
            'etat'               => 'en_attente',
            'motif'              => 'Mauvais service',
            'date_demande'       => now(),
            'id_doleance'        => $doleance->id_doleance,
            'id_demandeur'       => $demandeur->id_utilisateur,
            'id_service_propose' => $cible->id_service,
        ]);

        $this->connecter($this->superAdmin())
            ->deleteJson("/api/admin/services/{$cible->id_service}")
            ->assertStatus(409)
            ->assertJson(['code' => 'service_reaffectations']);

        $this->assertNotNull(Service::find($cible->id_service));
    }

    public function test_la_liste_indique_si_le_service_est_supprimable(): void
    {
        $vide = Service::factory()->create(['nom_service' => 'Supprimable']);
        $occupe = Service::factory()->create(['nom_service' => 'Occupé']);
        $this->doleance(['id_service' => $occupe->id_service]);

        $services = collect(
            $this->connecter($this->superAdmin())
                ->getJson('/api/admin/services')
                ->assertOk()
                ->json('services')
        );

        $ligneVide = $services->firstWhere('id_service', $vide->id_service);
        $ligneOccupee = $services->firstWhere('id_service', $occupe->id_service);

        $this->assertTrue($ligneVide['supprimable']);
        $this->assertNull($ligneVide['raison_blocage']);
        $this->assertFalse($ligneOccupee['supprimable']);
        $this->assertSame('service_doleances', $ligneOccupee['raison_blocage']);
    }
}

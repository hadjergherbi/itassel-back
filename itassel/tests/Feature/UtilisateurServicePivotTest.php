<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class UtilisateurServicePivotTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_la_migration_remplit_le_pivot_et_le_principal_y_figure(): void
    {
        $service = $this->serviceUsuel();
        $id = DB::table('utilisateurs')->insertGetId([
            'nom' => 'Pivot',
            'prenom' => 'Retro',
            'email' => 'pivot.retro@example.test',
            'mot_de_passe' => Hash::make('secret'),
            'role' => 'admin_service',
            'actif' => true,
            'id_service' => $service->id_service,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_utilisateur');

        $this->assertSame(0, DB::table('utilisateur_service')->where('id_utilisateur', $id)->count());

        Utilisateur::remplirPivotDepuisPrincipal();
        Utilisateur::remplirPivotDepuisPrincipal();

        $this->assertSame(1, DB::table('utilisateur_service')->where('id_utilisateur', $id)->count());
        $this->assertSame($service->id_service, (int) DB::table('utilisateur_service')->where('id_utilisateur', $id)->value('id_service'));

        $admin = Utilisateur::findOrFail($id);
        $this->assertContains($service->id_service, $admin->idsServices());
        $this->assertTrue($admin->gereLeService($service->id_service));
        $this->assertSame($admin->idsServices(), $admin->idsServices());
    }

    public function test_un_super_admin_n_a_ni_pivot_ni_service_principal(): void
    {
        $admin = $this->superAdmin();

        $this->assertNull($admin->id_service);
        $this->assertSame([], $admin->idsServices());
        $this->assertSame(0, DB::table('utilisateur_service')->where('id_utilisateur', $admin->id_utilisateur)->count());
    }

    public function test_un_admin_de_service_cree_par_le_modele_a_son_principal_dans_le_pivot(): void
    {
        $service = Service::factory()->create(['nom_service' => 'Domaine pivot']);
        $admin = Utilisateur::factory()->adminService($service)->create();

        $this->assertSame($service->id_service, $admin->id_service);
        $this->assertTrue(
            DB::table('utilisateur_service')
                ->where('id_utilisateur', $admin->id_utilisateur)
                ->where('id_service', $service->id_service)
                ->exists()
        );
    }
}

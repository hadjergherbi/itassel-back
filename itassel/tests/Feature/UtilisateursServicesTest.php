<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class UtilisateursServicesTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
    }

    public function test_creation_et_desactivation_avec_ou_sans_remplacant(): void
    {
        $acteur = $this->connecter($this->superAdmin());
        $service = Service::assignables()->orderBy('id_service')->first();

        $this->postJson('/api/admin/utilisateurs', [
            'nom' => 'Kaci',
            'prenom' => 'Lina',
            'email' => 'lina@itassel.test',
            'role' => 'admin_service',
            'id_service' => $service->id_service,
        ])->assertCreated();

        $cible = Utilisateur::where('email', 'lina@itassel.test')->first();
        $cible->mot_de_passe_defini_le = now();
        $cible->save();
        $service->update(['id_responsable' => $cible->id_utilisateur]);

        $doleance = $this->doleance([
            'id_service' => $service->id_service,
            'id_responsable' => $cible->id_utilisateur,
            'statut' => Statut::EN_COURS,
        ]);

        $this->postJson("/api/admin/utilisateurs/{$cible->id_utilisateur}/desactiver")
            ->assertOk()
            ->assertJsonPath('dossiers_transferes', 1);

        $this->assertNull($service->fresh()->id_responsable);
        $this->assertSame($service->id_service, $doleance->fresh()->id_service);
        $this->assertNull($doleance->fresh()->id_responsable);
    }

    public function test_dernier_super_admin_protege(): void
    {
        $gardien = $this->superAdmin(['email' => 'gardien@itassel.test']);
        $seul = $this->superAdmin(['email' => 'seul@itassel.test']);
        Utilisateur::where('role', 'super_admin')
            ->whereNotIn('id_utilisateur', [$gardien->id_utilisateur, $seul->id_utilisateur])
            ->update(['actif' => false]);

        $this->connecter($gardien)
            ->postJson("/api/admin/utilisateurs/{$seul->id_utilisateur}/desactiver")
            ->assertOk();

        $this->postJson("/api/admin/utilisateurs/{$gardien->id_utilisateur}/desactiver")
            ->assertStatus(422);

        $operateur = $this->adminService();
        $permission = Permission::where('code', 'utilisateurs.gerer')->first();
        $role = Role::where('code', 'admin_service')->first();
        DB::table('role_permission')->updateOrInsert([
            'id_role' => $role->id_role,
            'id_permission' => $permission->id_permission,
        ]);
        Utilisateur::viderCachePermissions('admin_service');

        $this->connecter($operateur)
            ->deleteJson("/api/admin/utilisateurs/{$gardien->id_utilisateur}")
            ->assertStatus(409)
            ->assertJson(['code' => 'dernier_super_admin']);
    }

    public function test_suppression_seulement_si_inutilise(): void
    {
        $acteur = $this->connecter($this->superAdmin());
        $neuf = $this->adminService(null, ['email' => 'neuf@itassel.test']);

        $this->deleteJson("/api/admin/utilisateurs/{$neuf->id_utilisateur}")
            ->assertOk()
            ->assertJson(['message' => 'Compte supprimé.']);

        $this->assertNotNull(Utilisateur::withTrashed()->find($neuf->id_utilisateur)?->supprime_le);
        $this->assertDatabaseMissing('utilisateurs', [
            'id_utilisateur' => $neuf->id_utilisateur,
            'email' => 'neuf@itassel.test',
            'supprime_le' => null,
        ]);

        $utilise = $this->adminService();
        $this->doleance(['id_responsable' => $utilise->id_utilisateur]);

        $this->deleteJson("/api/admin/utilisateurs/{$utilise->id_utilisateur}")
            ->assertStatus(409)
            ->assertJson(['code' => 'utilisateur_affecte']);
    }

    public function test_designation_propage_aux_dossiers_sans_responsable(): void
    {
        $this->connecter($this->superAdmin());
        $service = Service::assignables()->orderBy('id_service')->first();
        $service->update(['id_responsable' => null]);
        $admin = $this->adminService($service);
        $doleance = $this->doleance(['id_service' => $service->id_service, 'id_responsable' => null]);

        $this->putJson("/api/admin/services/{$service->id_service}/responsable", [
            'id_responsable' => $admin->id_utilisateur,
        ])->assertOk();

        $this->assertSame($admin->id_utilisateur, $doleance->fresh()->id_responsable);
        $this->assertSame($service->id_service, $doleance->fresh()->id_service);
    }

    public function test_tous_les_domaines_n_est_pas_assignable_a_un_admin(): void
    {
        $this->connecter($this->superAdmin());
        $transverse = Service::firstOrCreate(['nom_service' => Service::TOUS_LES_DOMAINES]);

        $this->postJson('/api/admin/utilisateurs', [
            'nom' => 'Transverse',
            'prenom' => 'Admin',
            'email' => 'transverse@itassel.test',
            'role' => 'admin_service',
            'id_service' => $transverse->id_service,
        ])->assertUnprocessable();

        $services = $this->getJson('/api/admin/services')->assertOk()->json('services');
        $ligne = collect($services)->firstWhere('nom_service', Service::TOUS_LES_DOMAINES);
        $this->assertNotNull($ligne);
        $this->assertFalse($ligne['assignable']);
        $this->assertSame([], $ligne['alertes']);
    }
}

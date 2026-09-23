<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_la_matrice_par_defaut_est_seedee(): void
    {
        $this->assertTrue(Role::where('code', 'super_admin')->exists());
        $super = $this->superAdmin();
        $this->assertTrue($super->peut('roles.gerer'));
        $this->assertTrue($this->adminService()->peut('doleances.voir'));
        $this->assertFalse($this->adminService()->peut('roles.gerer'));
    }

    public function test_retirer_une_permission_bloque_immediatement_l_endpoint(): void
    {
        $admin = $this->superAdmin();
        $role = Role::where('code', 'super_admin')->first();
        $codes = collect($admin->permissions())->reject(fn ($c) => $c === 'journal.voir')->values()->all();

        $this->connecter($admin)
            ->putJson("/api/admin/roles/{$role->code}/permissions", ['permissions' => $codes])
            ->assertOk();

        Cache::flush();
        Utilisateur::viderCachePermissions('super_admin');

        $this->connecter($admin->fresh())
            ->getJson('/api/admin/journaux')
            ->assertStatus(403)
            ->assertJson(['code' => 'permission_refusee']);
    }

    public function test_les_permissions_verrouillees_ne_peuvent_pas_etre_retirees(): void
    {
        $admin = $this->superAdmin();
        $codes = collect($admin->permissions())->reject(fn ($c) => $c === 'roles.gerer')->values()->all();

        $this->connecter($admin)
            ->putJson('/api/admin/roles/super_admin/permissions', ['permissions' => $codes])
            ->assertStatus(422)
            ->assertJson(['code' => 'permission_verrouillee']);
    }

    public function test_un_compte_desactive_voit_son_jeton_rejete(): void
    {
        $acteur = $this->superAdmin(['email' => 'garde@itassel.test']);
        $cible = $this->superAdmin(['email' => 'cible@itassel.test']);
        $tokenActeur = $acteur->createToken('a')->plainTextToken;
        $token = $cible->createToken('t')->plainTextToken;

        $this->withToken($tokenActeur)
            ->postJson("/api/admin/utilisateurs/{$cible->id_utilisateur}/desactiver")
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($token)->getJson('/api/admin/me')->assertStatus(401);
    }
}

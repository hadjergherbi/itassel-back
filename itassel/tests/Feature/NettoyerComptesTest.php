<?php

namespace Tests\Feature;

use App\Console\Commands\NettoyerComptes;
use App\Models\Journal;
use App\Models\Service;
use App\Models\Utilisateur;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class NettoyerComptesTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_le_seeder_ne_cree_que_les_deux_comptes_et_naffiche_pas_le_mot_de_passe(): void
    {
        $this->artisan('db:seed')
            ->doesntExpectOutputToContain((string) config('itassel.demo_password'))
            ->assertSuccessful();

        $this->assertSame(1, Utilisateur::where('role', 'super_admin')->where('actif', true)->count());
        $this->assertSame(1, Utilisateur::where('role', 'admin_service')->where('actif', true)->count());
        $this->assertDatabaseMissing('utilisateurs', ['email' => 'farid.merabet@itassel.dz']);
        $this->assertDatabaseMissing('utilisateurs', ['email' => 'samira.bensalem@itassel.dz']);

        $sport = Service::where('nom_service', 'Sport')->first();
        $this->assertSame('amine.kaddour@itassel.dz', $sport->responsable?->email);
        $this->assertNull(Service::where('nom_service', 'Jeunesse')->first()->id_responsable);
        $this->assertNull(Service::where('nom_service', 'Ressources humaines')->first()->id_responsable);
    }

    public function test_le_seeder_refuse_hors_local_si_le_mot_de_passe_est_absent(): void
    {
        $this->app['env'] = 'production';
        config(['itassel.demo_password' => null]);

        $this->expectException(RuntimeException::class);
        (new AdminSeeder())->run();
    }

    public function test_dry_run_necrit_rien(): void
    {
        $intrus = $this->adminService(
            Service::where('nom_service', 'Jeunesse')->first(),
            ['email' => 'intrus@itassel.dz']
        );

        $this->artisan('itassel:nettoyer-comptes', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Simulation : aucune modification.')
            ->expectsOutputToContain('intrus@itassel.dz');

        $frais = $intrus->fresh();
        $this->assertTrue($frais->actif);
        $this->assertNull($frais->supprime_le);
        $this->assertDatabaseMissing('journaux', ['action' => 'desactivation_utilisateur']);
        $this->assertDatabaseMissing('journaux', ['action' => 'suppression_utilisateur']);
    }

    public function test_apres_execution_il_reste_un_super_admin_et_un_admin_de_service(): void
    {
        $jeunesse = Service::where('nom_service', 'Jeunesse')->first();
        $lie = $this->adminService($jeunesse, ['email' => 'farid.merabet@itassel.dz']);
        $jeunesse->update(['id_responsable' => $lie->id_utilisateur]);
        $doleance = $this->doleance([
            'id_service'      => $jeunesse->id_service,
            'id_responsable'  => $lie->id_utilisateur,
        ]);
        $lie->createToken('session');

        $vide = $this->adminService($jeunesse, ['email' => 'vide@itassel.dz']);
        $idService = $doleance->id_service;
        $idResponsable = $doleance->id_responsable;

        $this->artisan('itassel:nettoyer-comptes', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, Utilisateur::where('role', 'super_admin')->where('actif', true)->count());
        $this->assertSame(1, Utilisateur::where('role', 'admin_service')->where('actif', true)->count());
        $this->assertTrue(Utilisateur::where('email', 'nour.belkacem@itassel.dz')->value('actif'));
        $this->assertTrue(Utilisateur::where('email', 'amine.kaddour@itassel.dz')->value('actif'));

        $lie = Utilisateur::withTrashed()->where('email', 'farid.merabet@itassel.dz')->first();
        $this->assertFalse($lie->actif);
        $this->assertNull($lie->supprime_le);
        $this->assertSame(0, $lie->tokens()->count());

        $vide = Utilisateur::withTrashed()->where('email', 'vide@itassel.dz')->first();
        $this->assertFalse($vide->actif);
        $this->assertNotNull($vide->supprime_le);
        $this->assertDatabaseHas('utilisateurs', ['email' => 'vide@itassel.dz']);

        $doleance = $doleance->fresh();
        $this->assertSame($idService, $doleance->id_service);
        $this->assertSame($idResponsable, $doleance->id_responsable);
        $this->assertNull($jeunesse->fresh()->id_responsable);
        $this->assertSame(
            'amine.kaddour@itassel.dz',
            Service::where('nom_service', 'Sport')->first()->responsable?->email
        );

        $this->assertTrue(
            Journal::where('categorie', 'utilisateur')
                ->where('detail', 'like', '%farid.merabet@itassel.dz%')
                ->exists()
        );
        $this->assertTrue(
            Journal::where('action', 'suppression_utilisateur')
                ->where('detail', 'like', '%vide@itassel.dz%')
                ->exists()
        );

        $journaux = Journal::count();
        $this->artisan('itassel:nettoyer-comptes', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Rien à modifier.');
        $this->assertSame($journaux, Journal::count());
        $this->assertNull(Utilisateur::withTrashed()->where('email', 'farid.merabet@itassel.dz')->first()->supprime_le);
        $this->assertNotNull(Utilisateur::withTrashed()->where('email', 'vide@itassel.dz')->first()->supprime_le);
    }

    public function test_refus_si_un_compte_a_garder_est_introuvable_inactif_ou_du_mauvais_role(): void
    {
        $intrus = $this->adminService(
            Service::where('nom_service', 'Jeunesse')->first(),
            ['email' => 'intrus@itassel.dz']
        );

        $this->artisan('itassel:nettoyer-comptes', [
            '--super'  => 'absent@itassel.dz',
            '--force'  => true,
        ])->assertFailed()->expectsOutputToContain('introuvable');
        $this->assertTrue($intrus->fresh()->actif);

        $nour = Utilisateur::where('email', 'nour.belkacem@itassel.dz')->first();
        $nour->actif = false;
        $nour->save();
        $this->artisan('itassel:nettoyer-comptes', ['--force' => true])
            ->assertFailed()
            ->expectsOutputToContain("n'est pas actif");
        $this->assertTrue($intrus->fresh()->actif);
        $nour->actif = true;
        $nour->save();

        $amine = Utilisateur::where('email', 'amine.kaddour@itassel.dz')->first();
        $amine->role = 'super_admin';
        $amine->save();
        $this->artisan('itassel:nettoyer-comptes', ['--force' => true])
            ->assertFailed()
            ->expectsOutputToContain("n'a pas le rôle admin_service");
        $this->assertTrue($intrus->fresh()->actif);
        $this->assertTrue($nour->fresh()->actif);
    }

    public function test_impossible_de_retirer_le_dernier_super_administrateur(): void
    {
        $nour = Utilisateur::where('email', 'nour.belkacem@itassel.dz')->first();
        $this->assertTrue(NettoyerComptes::retireraitLeDernierSuperAdmin(
            $nour->id_utilisateur,
            [$nour->id_utilisateur]
        ));

        $intrus = $this->superAdmin(['email' => 'intrus@itassel.dz']);

        $this->artisan('itassel:nettoyer-comptes', ['--force' => true])->assertSuccessful();

        $this->assertTrue($nour->fresh()->actif);
        $this->assertNull($nour->fresh()->supprime_le);
        $this->assertSame(1, Utilisateur::where('role', 'super_admin')->where('actif', true)->count());

        $intrus = Utilisateur::withTrashed()->find($intrus->id_utilisateur);
        $this->assertFalse($intrus->actif);
        $this->assertNotNull($intrus->supprime_le);
    }
}

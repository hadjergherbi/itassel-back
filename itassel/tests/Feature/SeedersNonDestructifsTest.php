<?php

namespace Tests\Feature;

use App\Models\ModeleMessage;
use App\Models\Nature;
use App\Models\ParametreNotification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class SeedersNonDestructifsTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_un_second_seed_preserve_les_modifications(): void
    {
        $statut = Statut::parCode(Statut::NOUVELLE);
        $statut->update([
            'libelle' => 'Nouvelle version',
            'couleur' => 'rouge',
            'message_citoyen' => 'Message modifié',
            'ordre' => 99,
        ]);

        $role = Role::where('code', 'admin_service')->firstOrFail();
        $permission = Permission::where('code', 'doleances.exporter')->firstOrFail();
        DB::table('role_permission')
            ->where('id_role', $role->id_role)
            ->where('id_permission', $permission->id_permission)
            ->delete();

        $nature = Nature::where('libelle', 'Suggestion')->firstOrFail();
        $nature->delete();

        $service = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $service->delete();

        $parametre = ParametreNotification::query()->firstOrFail();
        $canal = ! $parametre->canal_email;
        $parametre->update(['canal_email' => $canal]);

        $modele = ModeleMessage::query()->firstOrFail();
        $modele->update(['contenu' => 'Texte conservé par le seeder']);

        $this->seed();

        $statut = $statut->fresh();
        $this->assertSame('Nouvelle version', $statut->libelle);
        $this->assertSame('rouge', $statut->couleur);
        $this->assertSame('Message modifié', $statut->message_citoyen);
        $this->assertSame(99, $statut->ordre);

        $this->assertDatabaseMissing('role_permission', [
            'id_role' => $role->id_role,
            'id_permission' => $permission->id_permission,
        ]);
        $this->assertDatabaseMissing('natures', ['libelle' => 'Suggestion']);
        $this->assertDatabaseMissing('services', ['nom_service' => 'Jeunesse']);
        $this->assertSame($canal, $parametre->fresh()->canal_email);
        $this->assertSame('Texte conservé par le seeder', $modele->fresh()->contenu);
    }

    public function test_un_compte_demo_desactive_n_est_pas_reactive(): void
    {
        $demo = Utilisateur::where('email', 'demo.sport@itassel.dz')->firstOrFail();
        $hash = Hash::make('MotDePasseModifie1');
        $demo->forceFill([
            'actif' => false,
            'mot_de_passe' => $hash,
        ])->save();
        $demo->delete();

        $this->seed();

        $frais = Utilisateur::withTrashed()->where('email', 'demo.sport@itassel.dz')->firstOrFail();
        $this->assertNotNull($frais->supprime_le);
        $this->assertFalse($frais->actif);
        $this->assertTrue(Hash::check('MotDePasseModifie1', $frais->mot_de_passe));
    }

    public function test_creer_super_admin_affiche_le_mot_de_passe_une_fois(): void
    {
        Artisan::call('itassel:creer-super-admin', [
            'email' => 'rssi@itassel.dz',
            '--nom' => 'Responsable',
            '--prenom' => 'Sécurité',
        ]);

        $sortie = Artisan::output();
        $this->assertSame(1, preg_match('/affiché une seule fois\) : (.+)$/m', $sortie, $correspondance));
        $motDePasse = $correspondance[1];

        $this->assertDatabaseHas('utilisateurs', [
            'email' => 'rssi@itassel.dz',
            'role' => 'super_admin',
            'actif' => true,
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'rssi@itassel.dz',
            'mot_de_passe' => $motDePasse,
        ])->assertOk();

        $this->assertSame(1, Artisan::call('itassel:creer-super-admin', [
            'email' => 'rssi@itassel.dz',
        ]));
        $this->assertSame(1, Utilisateur::where('email', 'rssi@itassel.dz')->count());
    }
}

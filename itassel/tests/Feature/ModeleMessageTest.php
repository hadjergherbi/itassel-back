<?php

namespace Tests\Feature;

use App\Models\ModeleMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ModeleMessageTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        ModeleMessage::query()->delete();
    }

    public function test_creation_avec_chaque_type_d_usage(): void
    {
        $this->connecter($this->superAdmin());
        $usages = array_keys(config('itassel.messages_usages'));

        foreach ($usages as $usage) {
            $reponse = $this->postJson('/api/admin/parametres/modeles-message', [
                'titre' => "Modèle {$usage}",
                'contenu' => "Contenu pour {$usage}.",
                'usage' => $usage,
            ])->assertCreated()
                ->assertJsonPath('modele.usage', $usage)
                ->assertJsonPath('modele.usage_libelle', config("itassel.messages_usages.{$usage}.libelle"));

            $this->assertSame($usage, ModeleMessage::find($reponse->json('modele.id_modele'))?->type_usage);
        }

        $this->assertSame(count($usages), ModeleMessage::count());
    }

    public function test_rejet_d_un_type_invalide(): void
    {
        $this->connecter($this->superAdmin());

        $this->postJson('/api/admin/parametres/modeles-message', [
            'titre' => 'Invalide',
            'contenu' => 'Texte.',
            'usage' => 'inexistant',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['usage'])
            ->assertJsonPath('errors.usage.0', 'Le type d\'usage sélectionné n\'est pas valide.');
    }

    public function test_ancienne_valeur_reponse_est_convertie(): void
    {
        $this->connecter($this->superAdmin());

        $this->postJson('/api/admin/parametres/modeles-message', [
            'titre' => 'Ancien libellé réponse',
            'contenu' => 'Texte converti.',
            'usage' => 'Réponse',
        ])->assertCreated()
            ->assertJsonPath('modele.usage', 'reponse');

        $this->assertSame('reponse', ModeleMessage::where('titre', 'Ancien libellé réponse')->value('type_usage'));
    }

    public function test_ancienne_valeur_complement_via_type_usage_est_convertie(): void
    {
        $this->connecter($this->superAdmin());

        $this->postJson('/api/admin/parametres/modeles-message', [
            'titre' => 'Ancien libellé complément',
            'contenu' => 'Texte converti.',
            'type_usage' => 'Complément',
        ])->assertCreated()
            ->assertJsonPath('modele.usage', 'complement');

        $this->assertSame('complement', ModeleMessage::where('titre', 'Ancien libellé complément')->value('type_usage'));
    }

    public function test_liste_par_usage_complement_et_reponse(): void
    {
        $this->connecter($this->adminService());
        ModeleMessage::create(['titre' => 'Modèle complément', 'contenu' => 'Q ?', 'type_usage' => 'complement']);
        ModeleMessage::create(['titre' => 'Modèle réponse', 'contenu' => 'R.', 'type_usage' => 'reponse']);
        ModeleMessage::create(['titre' => 'Autre modèle', 'contenu' => 'A.', 'type_usage' => 'autre']);

        $this->getJson('/api/admin/modeles-message?usage=complement')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.usage', 'complement')
            ->assertJsonPath('0.usage_libelle', config('itassel.messages_usages.complement.libelle'))
            ->assertJsonStructure([['id', 'id_modele', 'titre', 'contenu', 'usage', 'usage_libelle']]);

        $this->getJson('/api/admin/modeles-message?usage=reponse')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.usage', 'reponse');
    }

    public function test_liste_sans_filtre_renvoie_tous_les_modeles(): void
    {
        $this->connecter($this->adminService());
        ModeleMessage::create(['titre' => 'A', 'contenu' => 'a', 'type_usage' => 'complement']);
        ModeleMessage::create(['titre' => 'B', 'contenu' => 'b', 'type_usage' => 'reponse']);

        $this->getJson('/api/admin/modeles-message')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_liste_accepte_ancienne_valeur_usage(): void
    {
        $this->connecter($this->adminService());
        ModeleMessage::create(['titre' => 'Nouveau code', 'contenu' => 'n', 'type_usage' => 'complement']);
        ModeleMessage::create(['titre' => 'Ancienne libellé', 'contenu' => 'a', 'type_usage' => 'Complément']);

        $this->getJson('/api/admin/modeles-message?usage=Complément')
            ->assertOk()
            ->assertJsonCount(2);

        $this->getJson('/api/admin/modeles-message?usage=complement')
            ->assertOk()
            ->assertJsonCount(2);

        $this->getJson('/api/admin/modeles-message?usage=inexistant')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_agent_avec_permission_repondre_peut_lister(): void
    {
        $admin = $this->adminService();
        $this->assertFalse($admin->peut('parametres.gerer'));
        $this->assertTrue($admin->peut('doleances.repondre'));

        ModeleMessage::create(['titre' => 'Réponse type', 'contenu' => 'OK', 'type_usage' => 'reponse']);

        $this->connecter($admin)
            ->getJson('/api/admin/modeles-message?usage=reponse')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_utilisateur_sans_permission_lecture_renvoie_403(): void
    {
        $admin = $this->adminService();
        $role = \App\Models\Role::where('code', 'admin_service')->firstOrFail();
        $codes = collect($admin->permissions())
            ->reject(fn ($c) => in_array($c, [
                'doleances.repondre',
                'complements.demander',
                'parametres.gerer',
            ], true))
            ->values()
            ->all();
        $role->permissions()->sync(
            \App\Models\Permission::whereIn('code', $codes)->pluck('id_permission')
        );
        \App\Models\Utilisateur::viderCachePermissions('admin_service');
        \Illuminate\Support\Facades\Cache::flush();

        $this->connecter($admin->fresh())
            ->getJson('/api/admin/modeles-message')
            ->assertStatus(403)
            ->assertJson(['code' => 'permission_refusee']);
    }

    public function test_filtre_par_usage(): void
    {
        $this->connecter($this->superAdmin());
        ModeleMessage::create(['titre' => 'A', 'contenu' => 'a', 'type_usage' => 'reponse']);
        ModeleMessage::create(['titre' => 'B', 'contenu' => 'b', 'type_usage' => 'complement']);

        $this->getJson('/api/admin/parametres/modeles-message?usage=complement')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.usage', 'complement');

        $this->getJson('/api/admin/modeles-message?usage=reponse')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.usage', 'reponse')
            ->assertJsonPath('0.usage_libelle', config('itassel.messages_usages.reponse.libelle'));
    }

    public function test_filtre_par_statut_inclut_autre(): void
    {
        $this->connecter($this->superAdmin());
        ModeleMessage::create(['titre' => 'Comp', 'contenu' => 'c', 'type_usage' => 'complement']);
        ModeleMessage::create(['titre' => 'Accusé', 'contenu' => 'a', 'type_usage' => 'accuse_reception']);
        ModeleMessage::create(['titre' => 'Libre', 'contenu' => 'l', 'type_usage' => 'autre']);
        ModeleMessage::create(['titre' => 'Finale', 'contenu' => 'f', 'type_usage' => 'reponse']);

        $codes = collect($this->getJson('/api/admin/modeles-message?statut=en_cours')
            ->assertOk()
            ->json())
            ->pluck('usage')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['accuse_reception', 'autre'], $codes);
        $this->assertNotContains('complement', $codes);
        $this->assertNotContains('reponse', $codes);
    }

    public function test_route_usages(): void
    {
        $this->connecter($this->superAdmin());

        $reponse = $this->getJson('/api/admin/messages-predefinis/usages')
            ->assertOk()
            ->assertJsonStructure([['code', 'libelle', 'statuts']]);

        $codes = collect($reponse->json())->pluck('code')->all();
        $this->assertSame(array_keys(config('itassel.messages_usages')), $codes);

        $autre = collect($reponse->json())->firstWhere('code', 'autre');
        $this->assertSame([], $autre['statuts']);
        $this->assertSame('Autre', $autre['libelle']);
    }

    public function test_conversion_des_anciennes_valeurs(): void
    {
        ModeleMessage::create(['titre' => 'Ancienne réponse', 'contenu' => 'x', 'type_usage' => 'Réponse']);
        ModeleMessage::create(['titre' => 'Ancien complément', 'contenu' => 'y', 'type_usage' => 'Complément']);
        ModeleMessage::create(['titre' => 'Ancienne conclusion', 'contenu' => 'z', 'type_usage' => 'conclusion']);

        $migration = require database_path('migrations/2026_09_29_230000_convertir_type_usage_modeles_message.php');
        $migration->up();

        $this->assertSame('reponse', ModeleMessage::where('titre', 'Ancienne réponse')->value('type_usage'));
        $this->assertSame('complement', ModeleMessage::where('titre', 'Ancien complément')->value('type_usage'));
        $this->assertSame('autre', ModeleMessage::where('titre', 'Ancienne conclusion')->value('type_usage'));

        $migration->down();

        $this->assertSame('Réponse', ModeleMessage::where('titre', 'Ancienne réponse')->value('type_usage'));
        $this->assertSame('Complément', ModeleMessage::where('titre', 'Ancien complément')->value('type_usage'));
        $this->assertSame('conclusion', ModeleMessage::where('titre', 'Ancienne conclusion')->value('type_usage'));
    }

    public function test_seeder_cree_un_modele_par_usage(): void
    {
        ModeleMessage::query()->delete();
        $this->seed(\Database\Seeders\ModeleMessageSeeder::class);

        $usages = array_keys(config('itassel.messages_usages'));
        $this->assertSame(count($usages), ModeleMessage::count());
        foreach ($usages as $usage) {
            $this->assertTrue(ModeleMessage::where('type_usage', $usage)->exists(), $usage);
        }
    }
}

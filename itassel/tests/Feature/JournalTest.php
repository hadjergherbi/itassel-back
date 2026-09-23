<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\Service;
use App\Models\Statut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_entrees_et_categories(): void
    {
        $admin = $this->superAdmin();
        $this->connecter($admin);
        $doleance = $this->doleance(['statut' => Statut::EN_COURS]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::RESOLUE)->id_statut,
            'message' => 'OK',
        ])->assertOk();

        $this->postJson("/api/admin/doleances/{$doleance->reference}/reponses", [
            'contenu' => 'Réponse au demandeur.',
        ])->assertCreated();

        $destination = Service::where('id_service', '!=', $doleance->id_service)->first();
        $ouvert = $this->doleance(['statut' => Statut::NOUVELLE]);
        $this->postJson("/api/admin/doleances/{$ouvert->reference}/reaffecter", [
            'id_service_destination' => $destination->id_service,
            'motif' => 'Rééquilibrage',
        ])->assertOk();

        $this->get('/api/admin/doleances/export')->assertOk();

        $this->postJson('/api/admin/utilisateurs', [
            'nom' => 'Journal',
            'prenom' => 'User',
            'email' => 'journal.user@itassel.test',
            'role' => 'super_admin',
        ])->assertCreated();

        $codes = $admin->fresh()->permissions();
        $this->putJson('/api/admin/roles/admin_service/permissions', [
            'permissions' => $codes,
        ])->assertOk();

        $this->assertSame('doleance', Journal::where('action', 'changement_statut')->value('categorie'));
        $this->assertSame('doleance', Journal::where('action', 'reponse')->value('categorie'));
        $this->assertSame('affectation', Journal::where('action', 'reaffectation')->value('categorie'));
        $this->assertSame('export', Journal::where('action', 'export_csv')->value('categorie'));
        $this->assertSame('utilisateur', Journal::where('action', 'creation_utilisateur')->value('categorie'));
        $this->assertSame('utilisateur', Journal::where('action', 'modification_permissions_role')->value('categorie'));
    }

    public function test_filtres_et_export_du_journal(): void
    {
        $this->connecter($this->superAdmin());
        $this->getJson('/api/admin/doleances');
        Journal::factory()->create([
            'action' => 'connexion',
            'categorie' => 'connexion',
            'resultat' => 'echec',
            'detail' => 'Mot de passe incorrect',
        ]);

        $this->getJson('/api/admin/journaux?categorie=connexion&resultat=echec')
            ->assertOk()
            ->assertJsonPath('data.0.categorie', 'connexion');

        $csv = $this->get('/api/admin/journaux/export')->assertOk()->getContent();
        $this->assertStringContainsString('Connexion', $csv);

        $this->getJson('/api/admin/journaux/tableau-de-bord')
            ->assertOk()
            ->assertJsonStructure([
                'actions_aujourdhui',
                'connexions_reussies_aujourdhui',
                'echecs_connexion_aujourdhui',
                'par_jour',
                'par_categorie',
                'echecs_recents',
            ]);
    }
}

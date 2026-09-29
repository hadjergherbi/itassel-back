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
    use ItasselHelpers, RefreshDatabase;

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
        $this->assertStringContainsString('Cible', $csv);
        $this->assertStringContainsString('Sensible', $csv);

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

    public function test_un_echec_connexion_echec_est_compte(): void
    {
        $this->connecter($this->superAdmin());
        Journal::factory()->create([
            'action' => 'connexion_echec',
            'categorie' => 'connexion',
            'resultat' => 'echec',
            'compte' => 'a***z@itassel.test',
            'adresse_ip' => '10.0.0.4',
            'date_action' => now(),
        ]);
        Journal::factory()->create([
            'action' => 'connexion',
            'categorie' => 'connexion',
            'resultat' => 'echec',
            'date_action' => now()->subMinute(),
        ]);

        $json = $this->getJson('/api/admin/journaux/tableau-de-bord')->assertOk()->json();

        $this->assertSame(2, $json['echecs_connexion_aujourdhui']);
        $this->assertSame('connexion_echec', $json['echecs_recents'][0]['action']);
    }

    public function test_periode_7j(): void
    {
        $this->connecter($this->superAdmin());
        Journal::factory()->create([
            'action' => 'connexion_echec',
            'categorie' => 'connexion',
            'resultat' => 'echec',
            'date_action' => now()->subDays(2),
        ]);
        Journal::factory()->create([
            'action' => 'connexion_echec',
            'categorie' => 'connexion',
            'resultat' => 'echec',
            'date_action' => now()->subDays(10),
        ]);

        $this->getJson('/api/admin/journaux/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('echecs_connexion_aujourdhui', 0);

        $reponse = $this->getJson('/api/admin/journaux/tableau-de-bord?periode=7j')->assertOk();
        $reponse->assertJsonPath('periode', '7j');
        $reponse->assertJsonPath('depuis', now()->subDays(7)->toDateString());
        $reponse->assertJsonPath('echecs_connexion_aujourdhui', 1);
        $this->assertCount(7, $reponse->json('par_jour'));

        $this->getJson('/api/admin/journaux/tableau-de-bord?periode=ailleurs')->assertStatus(422);
    }

    public function test_ip_suspectes_a_partir_de_trois_echecs(): void
    {
        $this->connecter($this->superAdmin());

        for ($i = 0; $i < 2; $i++) {
            Journal::factory()->create([
                'action' => 'connexion_echec',
                'categorie' => 'connexion',
                'resultat' => 'echec',
                'adresse_ip' => '192.0.2.10',
                'date_action' => now(),
            ]);
        }

        $this->getJson('/api/admin/journaux/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('ip_suspectes', []);

        Journal::factory()->create([
            'action' => 'connexion_echec',
            'categorie' => 'connexion',
            'resultat' => 'echec',
            'adresse_ip' => '192.0.2.10',
            'date_action' => now(),
        ]);

        $this->getJson('/api/admin/journaux/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('ip_suspectes.0.adresse_ip', '192.0.2.10')
            ->assertJsonPath('ip_suspectes.0.echecs', 3)
            ->assertJsonStructure(['ip_suspectes' => [['adresse_ip', 'echecs', 'derniere_tentative']]]);
    }

    public function test_par_categorie_contient_les_six_categories(): void
    {
        $this->connecter($this->superAdmin());

        $categories = $this->getJson('/api/admin/journaux/tableau-de-bord')
            ->assertOk()
            ->json('par_categorie');

        $this->assertSame(
            ['connexion', 'doleance', 'affectation', 'utilisateur', 'parametre', 'export'],
            collect($categories)->pluck('categorie')->all()
        );
        $this->assertCount(6, $categories);
    }

    public function test_la_cible_d_une_doleance_et_le_detail_lisible(): void
    {
        $this->connecter($this->superAdmin());
        $doleance = $this->doleance([
            'statut' => Statut::EN_COURS,
            'reference' => 'ITS-2026-7711',
        ]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::RESOLUE)->id_statut,
            'message' => 'Traité.',
        ])->assertOk();

        $ligne = $this->getJson('/api/admin/journaux?action=changement_statut')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('doleance', $ligne['cible']['type']);
        $this->assertSame($doleance->id_doleance, $ligne['cible']['id']);
        $this->assertSame('ITS-2026-7711', $ligne['cible']['libelle']);
        $this->assertSame('/admin/doleances/ITS-2026-7711', $ligne['cible']['lien']);
        $this->assertSame('ITS-2026-7711 : en_cours → resolue', $ligne['detail']);
        $this->assertSame('ITS-2026-7711 : En cours → Résolue', $ligne['detail_lisible']);
    }

    public function test_la_cible_d_un_service_supprime_est_nulle(): void
    {
        $service = Service::factory()->create(['nom_service' => 'Service effacé']);
        Journal::factory()->create([
            'action' => 'suppression_service',
            'categorie' => 'parametre',
            'resultat' => 'succes',
            'compte' => 'admin@itassel.test',
            'cible_type' => Service::class,
            'cible_id' => $service->id_service,
            'detail' => $service->nom_service,
        ]);
        $service->delete();

        $this->connecter($this->superAdmin())
            ->getJson('/api/admin/journaux?action=suppression_service')
            ->assertOk()
            ->assertJsonPath('data.0.cible', null)
            ->assertJsonPath('data.0.sensible', true);
    }

    public function test_le_filtre_sensible(): void
    {
        $this->connecter($this->superAdmin());
        Journal::factory()->create([
            'action' => 'connexion',
            'categorie' => 'connexion',
            'resultat' => 'succes',
            'compte' => 'normal@itassel.test',
        ]);
        Journal::factory()->create([
            'action' => 'export_pdf',
            'categorie' => 'export',
            'resultat' => 'succes',
            'compte' => 'export@itassel.test',
        ]);

        $sensibles = collect($this->getJson('/api/admin/journaux?sensible=1')->assertOk()->json('data'));
        $this->assertTrue($sensibles->contains('action', 'export_pdf'));
        $this->assertFalse($sensibles->contains('action', 'connexion'));
        $this->assertTrue($sensibles->every(fn (array $ligne) => $ligne['sensible'] === true));

        $ordinaires = collect($this->getJson('/api/admin/journaux?sensible=0')->assertOk()->json('data'));
        $this->assertTrue($ordinaires->contains('action', 'connexion'));
        $this->assertFalse($ordinaires->contains('action', 'export_pdf'));
    }

    public function test_la_fiche_journal_liste_les_autres_actions_du_jour(): void
    {
        $this->connecter($this->superAdmin());
        $compte = 'agent.journal@itassel.test';
        $courante = Journal::factory()->create([
            'compte' => $compte,
            'action' => 'connexion',
            'categorie' => 'connexion',
            'resultat' => 'succes',
            'date_action' => now(),
        ]);
        $memeJour = Journal::factory()->create([
            'compte' => $compte,
            'action' => 'deconnexion',
            'categorie' => 'connexion',
            'resultat' => 'succes',
            'date_action' => now(),
        ]);
        Journal::factory()->create([
            'compte' => 'autre@itassel.test',
            'action' => 'connexion',
            'categorie' => 'connexion',
            'resultat' => 'succes',
            'date_action' => now(),
        ]);

        $json = $this->getJson("/api/admin/journaux/{$courante->id_journal}")
            ->assertOk()
            ->json();

        $this->assertSame($courante->id_journal, $json['id_journal']);
        $this->assertArrayHasKey('cible', $json);
        $this->assertArrayHasKey('sensible', $json);
        $ids = collect($json['autres_actions'])->pluck('id_journal');
        $this->assertTrue($ids->contains($memeJour->id_journal));
        $this->assertFalse($ids->contains($courante->id_journal));
        $this->assertCount(1, $json['autres_actions']);
    }
}

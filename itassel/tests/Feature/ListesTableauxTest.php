<?php

namespace Tests\Feature;

use App\Models\Nature;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Statut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ListesTableauxTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_filtre_et_resume_sans_responsable_et_reaffectation(): void
    {
        $this->connecter($this->superAdmin());
        $service = Service::first();
        $service->update(['id_responsable' => null]);
        $autreService = Service::where('id_service', '!=', $service->id_service)->first();
        $this->adminService($autreService);
        $autreService->update(['id_responsable' => \App\Models\Utilisateur::where('id_service', $autreService->id_service)->value('id_utilisateur')]);
        $this->doleance(['id_service' => $service->id_service, 'statut' => Statut::NOUVELLE]);
        $autre = $this->doleance(['id_service' => $autreService->id_service, 'statut' => Statut::EN_COURS]);
        Reaffectation::factory()->create([
            'id_doleance' => $autre->id_doleance,
            'etat' => 'en_attente',
        ]);

        $liste = $this->getJson('/api/admin/doleances')->assertOk();
        $liste->assertJsonPath('resume.sans_responsable', 1);
        $this->assertGreaterThanOrEqual(1, $liste->json('resume.reaffectations_en_attente'));

        $filtre = $this->getJson('/api/admin/doleances?sans_responsable=1')->assertOk();
        $this->assertSame(1, $filtre->json('doleances.total'));

        $ligne = collect($liste->json('doleances.data'))->firstWhere('reference', $autre->reference);
        $this->assertTrue($ligne['reaffectation_en_attente']);
    }

    public function test_export_respecte_les_filtres(): void
    {
        $this->connecter($this->superAdmin());
        $this->doleance(['reference' => 'ITS-2026-0001', 'statut' => Statut::NOUVELLE]);
        $this->doleance(['reference' => 'ITS-2026-9999', 'statut' => Statut::RESOLUE]);

        $csv = $this->get('/api/admin/doleances/export?issue=nouvelle')->assertOk()->getContent();

        $this->assertStringContainsString('ITS-2026-0001', $csv);
        $this->assertStringNotContainsString('ITS-2026-9999', $csv);
    }

    public function test_un_admin_de_service_voit_ses_doleances_et_toutes_natures(): void
    {
        $sport = Service::where('nom_service', 'Sport')->first();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->first();
        $admin = $this->adminService($sport);
        $toutes = Nature::firstOrCreate(
            ['libelle' => Nature::TOUTES_NATURES],
            ['famille' => 'reclamation']
        );
        $reclamation = $this->natureUsuelle();

        $mienne = $this->doleance([
            'id_service' => $sport->id_service,
            'id_nature'  => $reclamation->id_nature,
            'reference'  => 'ITS-2026-5101',
        ]);
        $transversale = $this->doleance([
            'id_service' => $jeunesse->id_service,
            'id_nature'  => $toutes->id_nature,
            'reference'  => 'ITS-2026-5102',
        ]);
        $autre = $this->doleance([
            'id_service' => $jeunesse->id_service,
            'id_nature'  => $reclamation->id_nature,
            'reference'  => 'ITS-2026-5103',
        ]);

        $this->connecter($admin);
        $references = collect($this->getJson('/api/admin/doleances')->assertOk()->json('doleances.data'))
            ->pluck('reference');

        $this->assertContains($mienne->reference, $references);
        $this->assertContains($transversale->reference, $references);
        $this->assertNotContains($autre->reference, $references);

        $tableau = $this->getJson('/api/admin/tableau-de-bord?periode=6m')->assertOk()->json();
        $this->assertSame(2, $tableau['indicateurs']['total']);
        $this->assertSame(2, $tableau['indicateurs']['nouvelles']);
    }

    public function test_un_admin_de_service_voit_les_doleances_tous_les_domaines(): void
    {
        $sport = Service::where('nom_service', 'Sport')->first();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->first();
        $transverse = Service::firstOrCreate(['nom_service' => Service::TOUS_LES_DOMAINES]);
        $admin = $this->adminService($sport);
        $reclamation = $this->natureUsuelle();

        $mienne = $this->doleance([
            'id_service' => $sport->id_service,
            'id_nature'  => $reclamation->id_nature,
            'reference'  => 'ITS-2026-5201',
        ]);
        $tousDomaines = $this->doleance([
            'id_service' => $transverse->id_service,
            'id_nature'  => $reclamation->id_nature,
            'reference'  => 'ITS-2026-5202',
        ]);
        $autre = $this->doleance([
            'id_service' => $jeunesse->id_service,
            'id_nature'  => $reclamation->id_nature,
            'reference'  => 'ITS-2026-5203',
        ]);

        $this->connecter($admin);
        $references = collect($this->getJson('/api/admin/doleances')->assertOk()->json('doleances.data'))
            ->pluck('reference');

        $this->assertContains($mienne->reference, $references);
        $this->assertContains($tousDomaines->reference, $references);
        $this->assertNotContains($autre->reference, $references);

        $tableau = $this->getJson('/api/admin/tableau-de-bord?periode=6m')->assertOk()->json();
        $this->assertSame(2, $tableau['indicateurs']['total']);
        $this->assertSame(2, $tableau['indicateurs']['nouvelles']);
    }

    public function test_vue_globale_a_traiter_egale_nouvelles(): void
    {
        $admin = $this->connecter($this->superAdmin());
        $this->doleance(['statut' => Statut::NOUVELLE]);
        $this->doleance(['statut' => Statut::EN_COURS]);

        $json = $this->getJson('/api/admin/tableau-de-bord')->assertOk()->json();
        $this->assertArrayHasKey('vue_globale', $json);
        foreach ($json['vue_globale']['par_service'] as $ligne) {
            $this->assertSame($ligne['nouvelles'], $ligne['a_traiter']);
        }
        $this->assertSame($json['indicateurs']['nouvelles'], $json['vue_globale']['nouvelles']);
    }
}

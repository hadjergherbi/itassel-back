<?php

namespace Tests\Feature;

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

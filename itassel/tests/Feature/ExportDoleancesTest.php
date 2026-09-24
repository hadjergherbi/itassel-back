<?php

namespace Tests\Feature;

use App\Models\Nature;
use App\Models\Service;
use App\Models\Statut;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ExportDoleancesTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_apercu_et_exports_sont_limites_au_service(): void
    {
        $sport = Service::where('nom_service', 'Sport')->first();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->first();
        $admin = $this->adminService($sport);
        $nature = Nature::where('libelle', 'Réclamation')->first();

        $this->doleance([
            'id_service' => $sport->id_service,
            'id_nature'  => $nature->id_nature,
            'reference'  => 'ITS-2026-2001',
        ]);
        $this->doleance([
            'id_service' => $jeunesse->id_service,
            'id_nature'  => $nature->id_nature,
            'reference'  => 'ITS-2026-2002',
        ]);

        $this->connecter($admin);
        $query = 'date_debut=2026-01-01&date_fin=2026-09-24';

        $apercu = $this->getJson('/api/admin/doleances/export/apercu?'.$query)->assertOk()->json();
        $this->assertSame(1, $apercu['total']);

        $csv = $this->get('/api/admin/doleances/export?'.$query)->assertOk();
        $this->assertStringContainsString('ITS-2026-2001', $csv->getContent());
        $this->assertStringNotContainsString('ITS-2026-2002', $csv->getContent());

        $pdf = $this->get('/api/admin/doleances/export?format=pdf&'.$query)->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_apercu_par_nature_sans_filtre_et_total_filtre(): void
    {
        $service = Service::first();
        $admin = $this->adminService($service);
        $reclamation = Nature::where('libelle', 'Réclamation')->first();
        $suggestion = Nature::where('libelle', 'Suggestion')->first();

        $this->doleance(['id_service' => $service->id_service, 'id_nature' => $reclamation->id_nature]);
        $this->doleance(['id_service' => $service->id_service, 'id_nature' => $reclamation->id_nature]);
        $this->doleance(['id_service' => $service->id_service, 'id_nature' => $suggestion->id_nature]);

        $this->connecter($admin);
        $json = $this->getJson(
            '/api/admin/doleances/export/apercu?date_debut=2026-01-01&date_fin=2026-09-24&natures[]='.$reclamation->id_nature
        )->assertOk()->json();

        $this->assertSame(2, $json['total']);
        $parNature = collect($json['par_nature']);
        $this->assertGreaterThanOrEqual(4, $parNature->count());
        $this->assertSame(2, $parNature->firstWhere('id_nature', $reclamation->id_nature)['total']);
        $this->assertSame(1, $parNature->firstWhere('id_nature', $suggestion->id_nature)['total']);
        $this->assertSame(0, $parNature->firstWhere('libelle', 'Signalement')['total']);
    }

    public function test_apercu_rejette_les_periodes_invalides(): void
    {
        $this->connecter($this->adminService());

        $this->getJson('/api/admin/doleances/export/apercu?date_debut=2026-09-24&date_fin=2026-01-01')
            ->assertStatus(422)
            ->assertJsonFragment(['La date de fin doit être postérieure à la date de début.']);

        $this->getJson('/api/admin/doleances/export/apercu?date_debut=2025-01-01&date_fin=2026-09-24')
            ->assertStatus(422)
            ->assertJsonFragment(['La période ne peut pas dépasser 12 mois.']);
    }

    public function test_export_csv_et_pdf_ont_le_bon_nom_et_type(): void
    {
        $service = Service::where('nom_service', 'Sport')->first();
        $admin = $this->adminService($service);
        $this->doleance(['id_service' => $service->id_service, 'reference' => 'ITS-2026-3001']);

        $this->connecter($admin);
        $query = 'date_debut=2026-03-01&date_fin=2026-09-24';

        $csv = $this->get('/api/admin/doleances/export?'.$query)->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'doleances_sport_2026-03-01_2026-09-24.csv',
            $csv->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('Nature', $csv->getContent());

        $pdf = $this->get('/api/admin/doleances/export?format=pdf&'.$query)->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'doleances_sport_2026-03-01_2026-09-24.pdf',
            $pdf->headers->get('Content-Disposition')
        );
    }

    public function test_export_sans_resultat_renvoie_422(): void
    {
        $this->connecter($this->adminService());

        $this->getJson('/api/admin/doleances/export?date_debut=2026-01-01&date_fin=2026-01-31')
            ->assertStatus(422)
            ->assertJson(['message' => 'Aucune doléance ne correspond à ces critères.']);

        $this->getJson('/api/admin/doleances/export?format=pdf&date_debut=2026-01-01&date_fin=2026-01-31')
            ->assertStatus(422)
            ->assertJson(['message' => 'Aucune doléance ne correspond à ces critères.']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\Service;
use App\Support\GraphiqueCirculaire;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ExportPdfGraphiquesTest extends TestCase
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

    public function test_le_pdf_des_doleances_renvoie_un_pdf(): void
    {
        $service = Service::where('nom_service', 'Sport')->first();
        $this->doleance([
            'id_service' => $service->id_service,
            'reference'  => 'ITS-2026-4101',
            'date_depot' => '2026-06-15',
        ]);

        $this->connecter($this->adminService($service));

        $pdf = $this->get('/api/admin/doleances/export?format=pdf&date_debut=2026-03-01&date_fin=2026-09-24')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_anneau_renvoie_une_data_uri_png_ou_null_si_total_nul(): void
    {
        $this->assertNull(GraphiqueCirculaire::anneau([
            ['libelle' => 'Vide', 'valeur' => 0, 'couleur' => '#006b3f'],
        ]));

        $uri = GraphiqueCirculaire::anneau([
            ['libelle' => 'Résolues', 'valeur' => 3, 'couleur' => '#006b3f'],
            ['libelle' => 'En cours', 'valeur' => 1, 'couleur' => '#d97706'],
        ]);

        $this->assertIsString($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $binaire = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($binaire);
        $this->assertStringStartsWith("\x89PNG", $binaire);
    }

    public function test_le_pdf_du_journal_est_journalise(): void
    {
        $admin = $this->superAdmin();
        $this->connecter($admin);

        Journal::factory()->create([
            'compte'     => $admin->email,
            'action'     => 'connexion',
            'categorie'  => 'connexion',
            'resultat'   => 'succes',
            'date_action'=> '2026-06-15 10:00:00',
        ]);

        $pdf = $this->get('/api/admin/journaux/export?format=pdf&date_debut=2026-06-01&date_fin=2026-06-30')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'journal_2026-06-01_2026-06-30.pdf',
            (string) $pdf->headers->get('Content-Disposition')
        );
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->assertDatabaseHas('journaux', [
            'action' => 'export_journal',
            'detail' => 'PDF — 1 ligne(s)',
        ]);
    }

    public function test_un_pdf_de_journal_trop_volumineux_est_refuse(): void
    {
        $this->connecter($this->superAdmin());

        $maintenant = now()->toDateTimeString();
        $lot = [];
        for ($i = 0; $i < 5001; $i++) {
            $lot[] = [
                'date_action' => $maintenant,
                'compte'      => 'export@itassel.test',
                'action'      => 'connexion',
                'categorie'   => 'connexion',
                'adresse_ip'  => '127.0.0.1',
                'resultat'    => 'succes',
                'created_at'  => $maintenant,
                'updated_at'  => $maintenant,
            ];
            if (count($lot) === 500) {
                DB::table('journaux')->insert($lot);
                $lot = [];
            }
        }
        if ($lot !== []) {
            DB::table('journaux')->insert($lot);
        }

        $this->getJson('/api/admin/journaux/export?format=pdf')
            ->assertStatus(422)
            ->assertJsonPath('code', 'export_trop_volumineux');

        $this->assertDatabaseMissing('journaux', ['action' => 'export_journal']);
    }

    public function test_un_admin_service_nexporte_que_son_service(): void
    {
        $sport = Service::where('nom_service', 'Sport')->first();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->first();
        $admin = $this->adminService($sport);

        $this->doleance([
            'id_service' => $jeunesse->id_service,
            'reference'  => 'ITS-2026-4202',
            'date_depot' => '2026-06-15',
        ]);

        $this->connecter($admin);
        $query = 'format=pdf&date_debut=2026-03-01&date_fin=2026-09-24';

        $this->getJson('/api/admin/doleances/export?'.$query)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Aucune doléance ne correspond à ces critères.']);

        $this->doleance([
            'id_service' => $sport->id_service,
            'reference'  => 'ITS-2026-4201',
            'date_depot' => '2026-06-15',
        ]);

        $pdf = $this->get('/api/admin/doleances/export?'.$query)->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
    }
}

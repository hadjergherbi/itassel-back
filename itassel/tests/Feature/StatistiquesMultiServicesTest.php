<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\StatistiqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class StatistiquesMultiServicesTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_les_totaux_d_un_admin_valent_la_somme_de_ses_services(): void
    {
        $sport = $this->serviceUsuel();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $rh = Service::where('nom_service', 'Ressources humaines')->firstOrFail();
        $admin = $this->adminService($sport, ['email' => 'stats.multi@itassel.test']);
        $admin->services()->syncWithoutDetaching([$jeunesse->id_service]);
        $admin->oublierIdsServices();

        $this->doleance(['id_service' => $sport->id_service, 'reference' => 'ITS-2026-9101']);
        $this->doleance(['id_service' => $sport->id_service, 'reference' => 'ITS-2026-9102']);
        $this->doleance(['id_service' => $jeunesse->id_service, 'reference' => 'ITS-2026-9103']);
        $this->doleance(['id_service' => $rh->id_service, 'reference' => 'ITS-2026-9104']);

        $vue = StatistiqueService::vueGlobale($admin);
        $parNom = collect($vue['par_service'])->keyBy('nom_service');

        $this->assertSame(['Jeunesse', 'Sport'], $parNom->keys()->sort()->values()->all());
        $somme = (int) $parNom->sum('total');
        $this->assertSame($somme, $vue['total']);
        $this->assertSame(2, $parNom['Sport']['total']);
        $this->assertSame(1, $parNom['Jeunesse']['total']);
        $this->assertSame(3, $vue['total']);

        $csv = $this->connecter($admin)
            ->get('/api/admin/doleances/export?format=csv')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ITS-2026-9101', $csv);
        $this->assertStringContainsString('ITS-2026-9103', $csv);
        $this->assertStringNotContainsString('ITS-2026-9104', $csv);
    }
}

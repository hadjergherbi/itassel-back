<?php

namespace Tests\Feature;

use App\Models\Nature;
use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class VisibiliteServicesTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_un_admin_voit_les_doleances_de_ses_services_seulement(): void
    {
        $sport = $this->serviceUsuel();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $rh = Service::where('nom_service', 'Ressources humaines')->firstOrFail();
        $tous = Service::where('nom_service', Service::TOUS_LES_DOMAINES)->firstOrFail();
        $toutesNatures = Nature::where('libelle', Nature::TOUTES_NATURES)->firstOrFail();

        $admin = $this->adminPlusieurs($sport, $jeunesse);

        $chezSport = $this->doleance(['id_service' => $sport->id_service]);
        $chezJeunesse = $this->doleance(['id_service' => $jeunesse->id_service]);
        $chezRh = $this->doleance(['id_service' => $rh->id_service]);
        $transverse = $this->doleance([
            'id_service' => $rh->id_service,
            'id_nature' => $toutesNatures->id_nature,
        ]);
        $tousDomaines = $this->doleance(['id_service' => $tous->id_service]);

        $this->connecter($admin);
        $references = $this->referencesListe();

        $this->assertContains($chezSport->reference, $references);
        $this->assertContains($chezJeunesse->reference, $references);
        $this->assertContains($transverse->reference, $references);
        $this->assertContains($tousDomaines->reference, $references);
        $this->assertNotContains($chezRh->reference, $references);

        $this->getJson("/api/admin/doleances/{$chezRh->reference}")
            ->assertNotFound();

        $this->postJson("/api/admin/doleances/{$chezRh->reference}/notes", [
            'contenu' => 'Ne doit pas passer.',
        ])->assertNotFound();

        $filtre = $this->getJson('/api/admin/doleances?service='.$sport->id_service)->assertOk();
        $vues = collect($filtre->json('doleances.data'))->pluck('reference');
        $this->assertContains($chezSport->reference, $vues);
        $this->assertNotContains($chezJeunesse->reference, $vues);
        $this->assertNotContains($chezRh->reference, $vues);

        $this->getJson('/api/admin/doleances?service='.$rh->id_service)
            ->assertForbidden();
    }

    private function adminPlusieurs(Service $principal, Service $autre): Utilisateur
    {
        $admin = $this->adminService($principal);
        $admin->services()->syncWithoutDetaching([$autre->id_service]);
        $admin->oublierIdsServices();

        return $admin;
    }

    /**
     * @return list<string>
     */
    private function referencesListe(): array
    {
        return collect($this->getJson('/api/admin/doleances')->assertOk()->json('doleances.data'))
            ->pluck('reference')
            ->all();
    }
}

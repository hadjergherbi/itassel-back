<?php

namespace Tests\Feature;

use App\Models\Nature;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class EnregistrementSystemeTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        $this->connecter($this->superAdmin());
    }

    public function test_le_service_systeme_ne_peut_pas_etre_renomme_ni_supprime(): void
    {
        $service = Service::where('nom_service', 'Tous les domaines')->firstOrFail();

        $this->putJson("/api/admin/services/{$service->id_service}", [
            'nom_service' => 'Domaines',
        ])->assertStatus(409)->assertJsonPath('code', 'enregistrement_systeme');

        $this->assertSame('Tous les domaines', $service->fresh()->nom_service);

        $this->deleteJson("/api/admin/services/{$service->id_service}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'enregistrement_systeme');

        $this->assertNotNull($service->fresh());
    }

    public function test_un_doublon_du_service_systeme_est_refuse(): void
    {
        $this->postJson('/api/admin/services', [
            'nom_service' => '  TOUS les domaines  ',
        ])->assertStatus(409)->assertJsonPath('code', 'enregistrement_systeme');

        $this->postJson('/api/admin/services', [
            'nom_service' => 'Tous les domaines',
        ])->assertStatus(409)->assertJsonPath('code', 'enregistrement_systeme');
    }

    public function test_la_nature_systeme_ne_peut_pas_etre_renommee_ni_supprimee(): void
    {
        $nature = Nature::where('libelle', 'Toutes natures')->firstOrFail();

        $this->putJson("/api/admin/parametres/natures/{$nature->id_nature}", [
            'libelle' => 'Toutes les natures',
        ])->assertStatus(409)->assertJsonPath('code', 'enregistrement_systeme');

        $this->assertSame('Toutes natures', $nature->fresh()->libelle);

        $this->deleteJson("/api/admin/parametres/natures/{$nature->id_nature}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'enregistrement_systeme');

        $this->assertNotNull($nature->fresh());
    }

    public function test_un_doublon_de_la_nature_systeme_est_refuse(): void
    {
        $this->postJson('/api/admin/parametres/natures', [
            'libelle' => 'toutes nâtures',
            'famille' => 'reclamation',
        ])->assertStatus(409)->assertJsonPath('code', 'enregistrement_systeme');
    }
}

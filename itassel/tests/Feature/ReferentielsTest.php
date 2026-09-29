<?php

namespace Tests\Feature;

use App\Models\Doleance;
use App\Models\Nature;
use App\Models\Qualite;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ReferentielsTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_le_formulaire_n_expose_pas_les_valeurs_transverses(): void
    {
        $json = $this->getJson('/api/referentiels')->assertOk();

        $this->assertNotContains(Service::TOUS_LES_DOMAINES, collect($json->json('services'))->pluck('nom_service'));
        $this->assertNotContains(Nature::TOUTES_NATURES, collect($json->json('natures'))->pluck('libelle'));
    }

    public function test_le_depot_refuse_les_valeurs_transverses_et_un_telephone_invalide(): void
    {
        $transverse = Service::firstOrCreate(['nom_service' => Service::TOUS_LES_DOMAINES]);
        $toutes = Nature::firstOrCreate(
            ['libelle' => Nature::TOUTES_NATURES],
            ['famille' => 'reclamation']
        );

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'id_service' => $transverse->id_service,
        ]))->assertUnprocessable();

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'id_nature' => $toutes->id_nature,
        ]))->assertUnprocessable();

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'telephone' => '12345',
        ]))->assertUnprocessable();

        $this->assertSame(0, Doleance::count());
    }

    public function test_le_formulaire_expose_les_qualites_officielles_dans_l_ordre(): void
    {
        $libelles = collect($this->getJson('/api/referentiels')->assertOk()->json('qualites'))
            ->pluck('libelle')
            ->all();

        $this->assertSame(Qualite::LIBELLES, $libelles);
    }

    public function test_une_ancienne_qualite_utilisee_n_est_plus_selectionnable(): void
    {
        $obsolete = Qualite::create([
            'libelle' => 'Citoyen',
            'selectionnable' => true,
            'ordre' => 99,
        ]);
        $this->doleance(['id_qualite' => $obsolete->id_qualite]);

        Qualite::synchroniserReferentiel();

        $this->assertFalse($obsolete->fresh()->selectionnable);
        $this->assertDatabaseHas('qualites', ['libelle' => 'Citoyen']);

        $libelles = collect($this->getJson('/api/referentiels')->assertOk()->json('qualites'))
            ->pluck('libelle');
        $this->assertNotContains('Citoyen', $libelles);
        $this->assertSame(Qualite::LIBELLES, $libelles->all());

        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'id_qualite' => $obsolete->id_qualite,
        ]))->assertUnprocessable();
    }

    public function test_une_ancienne_qualite_inutilisee_est_supprimee(): void
    {
        Qualite::create([
            'libelle' => 'Sportif',
            'selectionnable' => true,
            'ordre' => 99,
        ]);

        Qualite::synchroniserReferentiel();

        $this->assertDatabaseMissing('qualites', ['libelle' => 'Sportif']);
    }
}

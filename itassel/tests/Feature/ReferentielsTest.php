<?php

namespace Tests\Feature;

use App\Models\Qualite;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ReferentielsTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_le_formulaire_inclut_tous_les_domaines(): void
    {
        $noms = collect($this->getJson('/api/referentiels')->assertOk()->json('services'))
            ->pluck('nom_service');

        $this->assertContains(Service::TOUS_LES_DOMAINES, $noms);
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
            'libelle'        => 'Citoyen',
            'selectionnable' => true,
            'ordre'          => 99,
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
            'libelle'        => 'Sportif',
            'selectionnable' => true,
            'ordre'          => 99,
        ]);

        Qualite::synchroniserReferentiel();

        $this->assertDatabaseMissing('qualites', ['libelle' => 'Sportif']);
    }
}

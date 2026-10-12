<?php

namespace Tests\Feature;

use App\Models\Reaffectation;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ReaffectationAnnulationTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_annuler_une_demande_inconnue_renvoie_404(): void
    {
        $this->connecter($this->adminService())
            ->postJson('/api/admin/reaffectations/999999/annuler', [
                'motif' => 'Je me suis trompé.',
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Demande de réaffectation introuvable.');
    }

    public function test_annuler_la_demande_d_un_autre_reste_interdit(): void
    {
        $origine = Service::where('nom_service', 'Sport')->first() ?? $this->serviceUsuel();
        $auteur = $this->adminService($origine);
        $autre = $this->adminService($origine, ['email' => 'autre.annulation@itassel.test']);
        $doleance = $this->doleance(['id_service' => $origine->id_service]);

        $demande = Reaffectation::create([
            'etat' => 'en_attente',
            'motif' => 'Mauvais service',
            'date_demande' => now(),
            'id_doleance' => $doleance->id_doleance,
            'id_demandeur' => $auteur->id_utilisateur,
        ]);

        $this->connecter($autre)
            ->postJson("/api/admin/reaffectations/{$demande->id_reaffectation}/annuler", [
                'motif' => 'Pas la mienne.',
            ])
            ->assertForbidden();

        $this->assertSame('en_attente', $demande->fresh()->etat);
    }
}

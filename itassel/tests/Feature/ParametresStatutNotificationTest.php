<?php

namespace Tests\Feature;

use App\Models\ParametreNotification;
use App\Models\Statut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ParametresStatutNotificationTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        $this->connecter($this->superAdmin());
    }

    public function test_la_couleur_d_un_statut_est_limitee_a_la_liste(): void
    {
        $statut = Statut::parCode(Statut::NOUVELLE);

        $this->putJson("/api/admin/parametres/statuts/{$statut->id_statut}", [
            'couleur' => 'rose',
        ])->assertUnprocessable();

        $this->assertNotSame('rose', $statut->fresh()->couleur);

        $this->putJson("/api/admin/parametres/statuts/{$statut->id_statut}", [
            'couleur' => 'turquoise',
        ])->assertOk();

        $this->assertSame('turquoise', $statut->fresh()->couleur);
    }

    public function test_une_ligne_inconnue_n_ecrit_aucune_notification(): void
    {
        $premiere = ParametreNotification::where('modifiable', true)->firstOrFail();
        $avant = (bool) $premiere->canal_email;

        $this->putJson('/api/admin/parametres/notifications', [
            [
                'evenement' => $premiere->evenement,
                'destinataire' => $premiere->destinataire,
                'canal_email' => ! $avant,
                'canal_app' => (bool) $premiere->canal_app,
            ],
            [
                'evenement' => 'inexistant',
                'destinataire' => 'demandeur',
                'canal_email' => true,
                'canal_app' => false,
            ],
        ])->assertUnprocessable();

        $this->assertSame($avant, (bool) $premiere->fresh()->canal_email);
    }
}

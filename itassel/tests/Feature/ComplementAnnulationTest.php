<?php

namespace Tests\Feature;

use App\Mail\ComplementAnnuleMail;
use App\Models\Complement;
use App\Models\Historique;
use App\Models\Journal;
use App\Models\Service;
use App\Models\Statut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ComplementAnnulationTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
    }

    public function test_annulation_chemin_heureux(): void
    {
        $admin = $this->superAdmin();
        $this->connecter($admin);
        $doleance = $this->doleance(['statut' => Statut::INFORMATION_DEMANDEE]);
        $complement = Complement::factory()->create([
            'id_doleance' => $doleance->id_doleance,
            'id_auteur' => $admin->id_utilisateur,
            'etat' => 'en_attente',
        ]);

        $this->postJson("/api/admin/complements/{$complement->id_complement}/annuler", [
            'motif' => 'Plus nécessaire.',
        ])->assertOk()
            ->assertJsonPath('message', 'Demande de complément annulée.')
            ->assertJsonPath('complement.etat', 'annule')
            ->assertJsonPath('complement.annule_par.id_utilisateur', $admin->id_utilisateur)
            ->assertJsonStructure(['email_envoye']);

        $this->assertSame(Statut::EN_COURS, $doleance->fresh()->statut->code);

        $visibles = Historique::where('id_doleance', $doleance->id_doleance)
            ->where('type_evenement', 'complement_annule')
            ->where('visible_demandeur', true)
            ->get();
        $this->assertCount(1, $visibles);
        $this->assertNull($visibles->first()->detail);

        $internes = Historique::where('type_evenement', 'complement_annule_motif')->get();
        $this->assertSame('Plus nécessaire.', $internes->first()->detail);
        $this->assertFalse($internes->first()->visible_demandeur);

        Mail::assertSent(ComplementAnnuleMail::class, function (ComplementAnnuleMail $mail) {
            return ! str_contains($mail->render(), 'Plus nécessaire.');
        });

        $this->assertTrue(Journal::where('action', 'complement_annule')->exists());
    }

    public function test_conflits_deja_repondu_et_deja_annule(): void
    {
        $this->connecter($this->superAdmin());
        $doleance = $this->doleance();
        $recu = Complement::factory()->recu()->create(['id_doleance' => $doleance->id_doleance]);
        $annule = Complement::factory()->create(['id_doleance' => $doleance->id_doleance, 'etat' => 'annule']);

        $this->postJson("/api/admin/complements/{$recu->id_complement}/annuler", ['motif' => 'x'])
            ->assertStatus(409)->assertJson(['code' => 'deja_repondu']);
        $this->postJson("/api/admin/complements/{$annule->id_complement}/annuler", ['motif' => 'x'])
            ->assertStatus(409)->assertJson(['code' => 'deja_annule']);
    }

    public function test_dossier_reaffecte_interdit_l_annulation(): void
    {
        $origine = Service::first();
        $destination = Service::where('id_service', '!=', $origine->id_service)->first();
        $admin = $this->adminService($origine);
        $doleance = $this->doleance(['id_service' => $destination->id_service]);
        $complement = Complement::factory()->create(['id_doleance' => $doleance->id_doleance]);

        \App\Models\Reaffectation::factory()->create([
            'id_doleance' => $doleance->id_doleance,
            'id_demandeur' => $admin->id_utilisateur,
            'etat' => 'acceptee',
            'id_service_destination' => $destination->id_service,
        ]);

        $this->connecter($admin)
            ->postJson("/api/admin/complements/{$complement->id_complement}/annuler", ['motif' => 'x'])
            ->assertStatus(403)
            ->assertJson(['code' => 'dossier_reaffecte']);
    }

    public function test_changer_statut_ne_filtre_plus_le_motif_au_citoyen(): void
    {
        $this->connecter($this->superAdmin());
        $doleance = $this->doleance(['statut' => Statut::INFORMATION_DEMANDEE]);
        Complement::factory()->create(['id_doleance' => $doleance->id_doleance, 'etat' => 'en_attente']);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::EN_COURS)->id_statut,
            'message' => 'Motif interne secret',
        ])->assertOk();

        $visible = Historique::where('id_doleance', $doleance->id_doleance)
            ->where('visible_demandeur', true)
            ->where('type_evenement', 'complement_annule')
            ->first();

        $this->assertNotNull($visible);
        $this->assertNull($visible->detail);
        Mail::assertSent(ComplementAnnuleMail::class, fn ($m) => ! str_contains($m->render(), 'Motif interne secret'));
    }
}

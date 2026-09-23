<?php

namespace Tests\Feature;

use App\Models\Complement;
use App\Models\Nature;
use App\Models\Statut;
use App\Services\StatistiqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class IssuesWorkflowTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_transitions_filtrees_par_famille(): void
    {
        $this->connecter($this->superAdmin());
        $reclamation = $this->doleance(['statut' => Statut::EN_COURS]);
        $demande = $this->doleance([
            'statut' => Statut::EN_COURS,
            'id_nature' => Nature::where('famille', 'demande')->first()->id_nature,
        ]);

        $codesRec = collect($this->getJson("/api/admin/doleances/{$reclamation->reference}")->json('transitions_autorisees'))->pluck('code');
        $codesDem = collect($this->getJson("/api/admin/doleances/{$demande->reference}")->json('transitions_autorisees'))->pluck('code');

        $this->assertTrue($codesRec->contains(Statut::RESOLUE));
        $this->assertFalse($codesRec->contains(Statut::REPONSE_APPORTEE));
        $this->assertTrue($codesDem->contains(Statut::REPONSE_APPORTEE));
        $this->assertFalse($codesDem->contains(Statut::RESOLUE));
    }

    public function test_resolue_refusee_pour_une_demande(): void
    {
        $this->connecter($this->superAdmin());
        $doleance = $this->doleance([
            'statut' => Statut::EN_COURS,
            'id_nature' => Nature::where('famille', 'demande')->first()->id_nature,
        ]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::RESOLUE)->id_statut,
            'message' => 'Réponse.',
        ])->assertStatus(422);
    }

    public function test_reponse_apportee_refusee_pour_une_reclamation(): void
    {
        $this->connecter($this->superAdmin());
        $doleance = $this->doleance(['statut' => Statut::EN_COURS]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::REPONSE_APPORTEE)->id_statut,
            'message' => 'Réponse.',
        ])->assertStatus(422);
    }

    public function test_champs_obligatoires_et_complement_bloque_les_issues(): void
    {
        $this->connecter($this->superAdmin());
        $doleance = $this->doleance(['statut' => Statut::EN_COURS]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::RESOLUE)->id_statut,
        ])->assertStatus(422);

        Complement::factory()->recu()->create(['id_doleance' => $doleance->id_doleance]);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::RESOLUE)->id_statut,
            'message' => 'OK',
        ])->assertStatus(422);

        $doleance->complements()->update(['etat' => 'examine']);

        $this->postJson("/api/admin/doleances/{$doleance->reference}/statut", [
            'id_statut' => Statut::parCode(Statut::RESOLUE)->id_statut,
            'message' => 'OK',
        ])->assertOk();

        $this->assertTrue(Statut::estFinal($doleance->fresh()->statut->code));
    }

    public function test_les_cinq_issues_sont_finales(): void
    {
        foreach (Statut::codesIssues() as $code) {
            $this->assertTrue(Statut::estFinal($code), $code);
            $this->assertSame([], config("itassel.transitions.{$code}"));
        }
    }

    public function test_le_suivi_citoyen_expose_message_citoyen(): void
    {
        $doleance = $this->doleance(['statut' => Statut::RESOLUE]);
        $jeton = 'session-test';
        cache()->put("suivi:session:{$jeton}", $doleance->id_doleance, 600);

        $this->getJson('/api/suivi/dossier?jeton_session='.$jeton)
            ->assertOk()
            ->assertJsonPath('statut.message_citoyen', 'Une réponse est disponible.')
            ->assertJsonStructure(['organisme_competent']);
    }

    public function test_reclassement_et_indicateurs(): void
    {
        $admin = $this->superAdmin();
        $this->connecter($admin);
        $ancien = $this->doleance(['statut' => Statut::NON_FONDEE]);
        $this->doleance(['statut' => Statut::RESOLUE]);
        $this->doleance(['statut' => Statut::NON_RETENUE]);

        $this->postJson("/api/admin/doleances/{$ancien->reference}/reclasser", [
            'id_statut' => Statut::parCode(Statut::HORS_COMPETENCE)->id_statut,
            'motif' => 'Requalification.',
        ])->assertOk();

        $issues = StatistiqueService::issues($admin);
        $this->assertSame(1, $issues['resolue']);
        $this->assertSame(1, $issues['non_retenue']);
        $this->assertSame(0.5, $issues['taux_resolution']);
    }
}

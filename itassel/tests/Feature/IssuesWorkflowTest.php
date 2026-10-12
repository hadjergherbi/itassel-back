<?php

namespace Tests\Feature;

use App\Models\Complement;
use App\Models\Historique;
use App\Models\Nature;
use App\Models\PieceJointe;
use App\Models\Reponse;
use App\Models\Statut;
use App\Services\StatistiqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class IssuesWorkflowTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

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

    public function test_le_suivi_citoyen_n_expose_que_la_liste_blanche(): void
    {
        $doleance = $this->doleance(['statut' => Statut::RESOLUE]);
        $jeton = 'session-test';
        cache()->put("suivi:session:{$jeton}", $doleance->id_doleance, 600);

        $this->getJson('/api/suivi/dossier', ['X-Suivi-Token' => $jeton])
            ->assertOk()
            ->assertJsonPath('statut.code', Statut::RESOLUE)
            ->assertJsonPath('statut.libelle', 'Résolue')
            ->assertJsonMissingPath('statut.message_citoyen')
            ->assertJsonMissingPath('organisme_competent')
            ->assertJsonMissingPath('email');
    }

    public function test_le_suivi_citoyen_masque_les_cles_internes(): void
    {
        $admin = $this->superAdmin();
        $initiale = $this->doleance();
        $doleance = $this->doleance([
            'statut' => Statut::INFORMATION_DEMANDEE,
            'id_responsable' => $admin->id_utilisateur,
            'id_doleance_initial' => $initiale->id_doleance,
            'email' => 'citoyen@example.test',
            'telephone' => '0551112233',
        ]);
        $statut = Statut::parCode(Statut::INFORMATION_DEMANDEE);
        Historique::create([
            'date_evenement' => now(),
            'type_evenement' => 'changement_statut',
            'detail' => 'Information demandée',
            'visible_demandeur' => true,
            'id_doleance' => $doleance->id_doleance,
            'id_utilisateur' => $admin->id_utilisateur,
            'id_statut_apres' => $statut->id_statut,
        ]);
        Reponse::create([
            'contenu' => 'Réponse visible',
            'date_publication' => now(),
            'id_doleance' => $doleance->id_doleance,
            'id_auteur' => $admin->id_utilisateur,
        ]);
        Complement::factory()->create([
            'id_doleance' => $doleance->id_doleance,
            'id_auteur' => $admin->id_utilisateur,
            'etat' => 'en_attente',
            'question' => 'Précisez.',
        ]);

        $jeton = 'session-fuite';
        cache()->put("suivi:session:{$jeton}", $doleance->id_doleance, 600);

        $corps = $this->getJson('/api/suivi/dossier', ['X-Suivi-Token' => $jeton])
            ->assertOk()
            ->assertJsonPath('reference', $doleance->reference)
            ->assertJsonPath('historique.0.type_evenement', 'changement_statut')
            ->assertJsonPath('reponses.0.contenu', 'Réponse visible')
            ->assertJsonPath('complements.0.question', 'Précisez.')
            ->json();

        $this->assertClesAbsentes($corps, [
            'id_responsable',
            'id_service',
            'email',
            'telephone',
            'id_utilisateur',
            'id_auteur',
            'id_doleance_initial',
        ]);

        $piece = new PieceJointe(['chemin' => 'prive/secret.pdf', 'nom_fichier' => 'secret.pdf']);
        $this->assertArrayNotHasKey('chemin', $piece->toArray());
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $donnee
     * @param  list<string>  $interdites
     */
    private function assertClesAbsentes(array $donnee, array $interdites): void
    {
        foreach ($donnee as $cle => $valeur) {
            if (is_string($cle)) {
                $this->assertNotContains($cle, $interdites, "Clé interdite présente : {$cle}");
            }
            if (is_array($valeur)) {
                $this->assertClesAbsentes($valeur, $interdites);
            }
        }
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

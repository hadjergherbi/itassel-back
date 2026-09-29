<?php

namespace Tests\Feature;

use App\Models\Complement;
use App\Models\Historique;
use App\Models\Nature;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Statut;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class TableauDeBordServiceTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_un_admin_de_service_ne_voit_pas_les_chiffres_d_un_autre(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();
        $adminSport = $this->adminService($sport, ['email' => 'sport@itassel.test']);
        $this->adminService($jeunesse, ['email' => 'jeunesse@itassel.test']);

        $this->doleance(['id_service' => $sport->id_service, 'statut' => Statut::NOUVELLE]);
        $this->doleance(['id_service' => $jeunesse->id_service, 'statut' => Statut::NOUVELLE]);
        $this->doleance(['id_service' => $jeunesse->id_service, 'statut' => Statut::EN_COURS]);

        $json = $this->connecter($adminSport)
            ->getJson('/api/admin/tableau-de-bord?periode=6m')
            ->assertOk()
            ->json();

        $this->assertSame(1, $json['indicateurs']['nouvelles']);
        $this->assertSame(0, $json['indicateurs']['en_cours']);
        $this->assertSame(1, $json['indicateurs']['total']);
        $this->assertArrayNotHasKey('vue_globale', $json);
    }

    public function test_la_periode_change_total_et_par_mois(): void
    {
        $service = Service::first();
        $admin = $this->adminService($service);

        $this->doleance([
            'id_service' => $service->id_service,
            'date_depot' => now()->subDays(10),
        ]);
        $this->doleance([
            'id_service' => $service->id_service,
            'date_depot' => now()->subMonths(8),
        ]);

        $j30 = $this->connecter($admin)->getJson('/api/admin/tableau-de-bord?periode=30j')->assertOk()->json();
        $j6m = $this->getJson('/api/admin/tableau-de-bord?periode=6m')->assertOk()->json();
        $annee = $this->getJson('/api/admin/tableau-de-bord?periode=annee')->assertOk()->json();

        $this->assertSame('Sur 30 jours', $j30['periode']['libelle']);
        $this->assertSame('Sur 6 mois', $j6m['periode']['libelle']);
        $this->assertSame('Depuis janvier', $annee['periode']['libelle']);
        $this->assertSame(1, $j30['indicateurs']['total']);
        $this->assertSame(1, $j6m['indicateurs']['total']);
        $this->assertSame(2, $annee['indicateurs']['total']);
        $this->assertCount(6, $j30['par_mois']);
        $this->assertCount(6, $j6m['par_mois']);
        $this->assertCount(12, $annee['par_mois']);
        $this->assertTrue(collect($j6m['par_mois'])->firstWhere('mois', '2026-09')['en_cours']);
    }

    public function test_priorites_et_seuils(): void
    {
        $service = Service::first();
        $admin = $this->adminService($service);
        $idInfo = Statut::parCode(Statut::INFORMATION_DEMANDEE)->id_statut;

        $nouvelleOk = $this->doleance([
            'id_service' => $service->id_service,
            'statut' => Statut::NOUVELLE,
            'date_depot' => now()->subDays(2),
        ]);
        $nouvelleRetard = $this->doleance([
            'id_service' => $service->id_service,
            'statut' => Statut::NOUVELLE,
            'date_depot' => now()->subDays(6),
        ]);
        $info = $this->doleance([
            'id_service' => $service->id_service,
            'statut' => Statut::INFORMATION_DEMANDEE,
            'date_depot' => now()->subDays(20),
        ]);
        Historique::create([
            'date_evenement' => now()->subDays(16),
            'type_evenement' => 'changement_statut',
            'id_doleance' => $info->id_doleance,
            'id_statut_apres' => $idInfo,
            'visible_demandeur' => false,
        ]);
        Complement::factory()->recu()->create([
            'id_doleance' => $nouvelleOk->id_doleance,
            'id_auteur' => $admin->id_utilisateur,
        ]);

        $json = $this->connecter($admin)->getJson('/api/admin/tableau-de-bord')->assertOk()->json();

        $this->assertSame(1, $json['priorites']['complements_a_examiner']);
        $this->assertSame(1, $json['priorites']['nouvelles_en_retard']);
        $this->assertSame(1, $json['priorites']['informations_sans_reponse']);
        $this->assertSame(5, $json['priorites']['seuils']['nouvelle_jours']);
        $this->assertSame(15, $json['priorites']['seuils']['information_jours']);
        $this->assertNotSame($nouvelleRetard->reference, $nouvelleOk->reference);
    }

    public function test_delai_moyen_depuis_historiques(): void
    {
        $service = Service::first();
        $admin = $this->adminService($service);
        $idResolue = Statut::parCode(Statut::RESOLUE)->id_statut;

        $vide = $this->connecter($admin)->getJson('/api/admin/tableau-de-bord')->assertOk()->json();
        $this->assertNull($vide['indicateurs']['delai_moyen_jours']);

        $doleance = $this->doleance([
            'id_service' => $service->id_service,
            'statut' => Statut::RESOLUE,
            'date_depot' => now()->subDays(10),
        ]);
        Historique::create([
            'date_evenement' => now()->subDays(4),
            'type_evenement' => 'changement_statut',
            'id_doleance' => $doleance->id_doleance,
            'id_statut_apres' => $idResolue,
            'visible_demandeur' => true,
        ]);

        $json = $this->getJson('/api/admin/tableau-de-bord?periode=30j')->assertOk()->json();
        $this->assertEquals(6.0, $json['indicateurs']['delai_moyen_jours']);
    }

    public function test_dernieres_a_traiter_triees_par_anciennete(): void
    {
        $service = Service::first();
        $admin = $this->adminService($service);

        $ancienne = $this->doleance([
            'id_service' => $service->id_service,
            'statut' => Statut::NOUVELLE,
            'date_depot' => now()->subDays(12),
            'nom' => 'Ancienne',
        ]);
        $this->doleance([
            'id_service' => $service->id_service,
            'statut' => Statut::EN_COURS,
            'date_depot' => now()->subDays(3),
            'nom' => 'Recente',
        ]);
        $this->doleance([
            'id_service' => $service->id_service,
            'statut' => Statut::RESOLUE,
            'date_depot' => now()->subDays(20),
            'nom' => 'Resolue',
        ]);

        $json = $this->connecter($admin)->getJson('/api/admin/tableau-de-bord')->assertOk()->json();
        $refs = collect($json['dernieres'])->pluck('reference')->all();

        $this->assertSame($ancienne->reference, $refs[0]);
        $this->assertNotContains('Resolue', collect($json['dernieres'])->pluck('nom')->all());
        $this->assertSame(12, $json['dernieres'][0]['age_jours']);
        $this->assertSame('rouge', $json['dernieres'][0]['niveau_age']);
        $this->assertSame('normal', $json['dernieres'][1]['niveau_age']);
    }

    public function test_mes_reaffectations_sont_celles_de_l_utilisateur(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();
        $admin = $this->adminService($sport);
        $autre = $this->adminService($jeunesse);
        $doleance = $this->doleance(['id_service' => $sport->id_service]);
        $autreDossier = $this->doleance(['id_service' => $jeunesse->id_service]);

        $mienne = Reaffectation::factory()->create([
            'id_doleance' => $doleance->id_doleance,
            'id_demandeur' => $admin->id_utilisateur,
            'id_service_propose' => $jeunesse->id_service,
            'etat' => 'refusee',
            'date_decision' => now(),
            'motif' => 'Hors périmètre',
        ]);
        Historique::create([
            'date_evenement' => now(),
            'type_evenement' => 'reaffectation_refusee',
            'detail' => 'Refus : compétence jeunesse',
            'id_doleance' => $doleance->id_doleance,
            'visible_demandeur' => false,
        ]);
        Reaffectation::factory()->create([
            'id_doleance' => $autreDossier->id_doleance,
            'id_demandeur' => $autre->id_utilisateur,
            'id_service_propose' => $sport->id_service,
            'etat' => 'en_attente',
        ]);

        $json = $this->connecter($admin)->getJson('/api/admin/tableau-de-bord')->assertOk()->json();

        $this->assertCount(1, $json['mes_reaffectations']);
        $this->assertSame($mienne->id_reaffectation, $json['mes_reaffectations'][0]['id_reaffectation']);
        $this->assertSame('Refus : compétence jeunesse', $json['mes_reaffectations'][0]['motif_refus']);
        $this->assertSame(0, $json['mes_reaffectations_en_attente']);
    }

    public function test_filtres_liste_age_min_info_et_natures(): void
    {
        $service = Service::first();
        $admin = $this->adminService($service);
        $natureA = Nature::where('libelle', 'Réclamation')->first();
        $natureB = Nature::where('libelle', 'Suggestion')->first();
        $idInfo = Statut::parCode(Statut::INFORMATION_DEMANDEE)->id_statut;

        $this->doleance([
            'id_service' => $service->id_service,
            'id_nature' => $natureA->id_nature,
            'date_depot' => now()->subDays(8),
            'reference' => 'ITS-2026-1001',
        ]);
        $this->doleance([
            'id_service' => $service->id_service,
            'id_nature' => $natureB->id_nature,
            'date_depot' => now()->subDays(1),
            'reference' => 'ITS-2026-1002',
        ]);
        $info = $this->doleance([
            'id_service' => $service->id_service,
            'id_nature' => $natureA->id_nature,
            'statut' => Statut::INFORMATION_DEMANDEE,
            'date_depot' => now()->subDays(20),
            'reference' => 'ITS-2026-1003',
        ]);
        Historique::create([
            'date_evenement' => now()->subDays(16),
            'type_evenement' => 'changement_statut',
            'id_doleance' => $info->id_doleance,
            'id_statut_apres' => $idInfo,
            'visible_demandeur' => false,
        ]);

        $this->connecter($admin);

        $age = $this->getJson('/api/admin/doleances?age_min=5')->assertOk();
        $this->assertSame(2, $age->json('doleances.total'));

        $natures = $this->getJson('/api/admin/doleances?natures[]='.$natureB->id_nature)->assertOk();
        $this->assertSame(1, $natures->json('doleances.total'));
        $this->assertSame('ITS-2026-1002', $natures->json('doleances.data.0.reference'));

        $infoSans = $this->getJson('/api/admin/doleances?info_sans_reponse=1')->assertOk();
        $this->assertSame(1, $infoSans->json('doleances.total'));
        $this->assertSame('ITS-2026-1003', $infoSans->json('doleances.data.0.reference'));
    }

    private function deuxServices(): array
    {
        return [
            Service::where('nom_service', 'Sport')->first(),
            Service::where('nom_service', 'Jeunesse')->first(),
        ];
    }
}

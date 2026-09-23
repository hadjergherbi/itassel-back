<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\Nature;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use App\Support\Acces;
use App\Support\DoleanceFiltre;
use Illuminate\Support\Facades\Schema;

class StatistiqueService
{
    public static function vueGlobale(Utilisateur $utilisateur): array
    {
        $visibles = Acces::doleancesVisibles($utilisateur);
        $statuts = Statut::all()->keyBy('code');
        $totauxParStatut = (clone $visibles)
            ->selectRaw('id_statut, COUNT(*) AS total')
            ->groupBy('id_statut')
            ->pluck('total', 'id_statut');

        $parCode = [];
        foreach ($statuts as $code => $statut) {
            $parCode[$code] = (int) ($totauxParStatut[$statut->id_statut] ?? 0);
        }

        $depuis = (clone $visibles)->min('date_depot');
        $total = (clone $visibles)->count();
        $nouvelles = $parCode[Statut::NOUVELLE] ?? 0;
        $resolues = $parCode[Statut::RESOLUE] ?? 0;

        $parService = Service::orderBy('nom_service')->get()->map(function (Service $service) use ($utilisateur) {
            $base = Acces::doleancesVisibles($utilisateur)->where('id_service', $service->id_service);
            $parStatut = (clone $base)
                ->selectRaw('id_statut, COUNT(*) AS total')
                ->groupBy('id_statut')
                ->pluck('total', 'id_statut');

            $codes = Statut::all()->keyBy('id_statut');
            $nouvelles = 0;
            $enCours = 0;
            $resolues = 0;
            $autres = 0;
            foreach ($parStatut as $idStatut => $total) {
                $code = $codes[$idStatut]->code ?? '';
                if ($code === Statut::NOUVELLE) {
                    $nouvelles += $total;
                } elseif ($code === Statut::EN_COURS) {
                    $enCours += $total;
                } elseif ($code === Statut::RESOLUE) {
                    $resolues += $total;
                } else {
                    $autres += $total;
                }
            }

            return [
                'id_service'  => $service->id_service,
                'nom_service' => $service->nom_service,
                'total'       => (clone $base)->count(),
                'nouvelles'   => $nouvelles,
                'en_cours'    => $enCours,
                'resolues'    => $resolues,
                'autres'      => $autres,
                'a_traiter'   => $nouvelles,
            ];
        })->values();

        $denom = $resolues + ($parCode[Statut::NON_RETENUE] ?? 0);

        return [
            'nouvelles'                 => $nouvelles,
            'en_cours'                  => $parCode[Statut::EN_COURS] ?? 0,
            'resolues'                  => $resolues,
            'total'                     => $total,
            'depuis'                    => $depuis ? substr((string) $depuis, 0, 10) : now()->toDateString(),
            'reaffectations_en_attente' => Reaffectation::where('etat', 'en_attente')->count(),
            'services'                  => Service::count(),
            'utilisateurs_actifs'       => Utilisateur::where('actif', true)->whereNotNull('mot_de_passe_defini_le')->count(),
            'comptes_desactives'        => Utilisateur::where('actif', false)->count(),
            'invitations_en_attente'    => Utilisateur::where('actif', true)->whereNull('mot_de_passe_defini_le')->count(),
            'par_service'               => $parService,
            'taux_resolution'           => $denom > 0 ? round($resolues / $denom, 4) : null,
        ];
    }

    public static function issues(Utilisateur $utilisateur): array
    {
        $visibles = Acces::doleancesVisibles($utilisateur);
        $statuts = Statut::all()->keyBy('code');
        $totaux = (clone $visibles)
            ->selectRaw('id_statut, COUNT(*) AS total')
            ->groupBy('id_statut')
            ->pluck('total', 'id_statut');

        $compte = function (string $code) use ($statuts, $totaux) {
            $id = $statuts[$code]->id_statut ?? null;

            return $id ? (int) ($totaux[$id] ?? 0) : 0;
        };

        $parIssue = [];
        foreach (Statut::codesIssues() as $code) {
            $parIssue[$code] = $compte($code);
        }

        $aReclasserQuery = DoleanceFiltre::appliquerAReclasser(clone $visibles);
        $aReclasserTotal = (clone $aReclasserQuery)->count();
        $anciens = (clone $visibles)
            ->whereHas('statut', fn ($q) => $q->whereIn('code', [Statut::NON_FONDEE, Statut::CLOTUREE]))
            ->count();
        $requalifier = (clone $visibles)
            ->whereHas('statut', fn ($q) => $q->where('code', Statut::RESOLUE))
            ->whereHas('nature', fn ($n) => $n->where('famille', 'demande'))
            ->count();

        $reclamationIds = Nature::where('famille', 'reclamation')->pluck('id_nature');
        $resoluesRec = (clone $visibles)
            ->whereIn('id_nature', $reclamationIds)
            ->whereHas('statut', fn ($q) => $q->where('code', Statut::RESOLUE))
            ->count();
        $nonRetenuesRec = (clone $visibles)
            ->whereIn('id_nature', $reclamationIds)
            ->whereHas('statut', fn ($q) => $q->where('code', Statut::NON_RETENUE))
            ->count();
        $denom = $resoluesRec + $nonRetenuesRec;

        $ouverts = (clone $visibles)
            ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
            ->count();

        return array_merge($parIssue, [
            'termines_total'  => array_sum($parIssue) + $aReclasserTotal,
            'a_reclasser'     => [
                'total'                 => $aReclasserTotal,
                'anciens_classements'   => $anciens,
                'resolus_a_requalifier' => $requalifier,
            ],
            'ouverts'         => $ouverts,
            'taux_resolution' => $denom > 0 ? round($resoluesRec / $denom, 4) : null,
        ]);
    }

    public static function activite(): array
    {
        $debutJour = now()->startOfDay();
        $driver = Schema::getConnection()->getDriverName();
        $exprJour = $driver === 'sqlite'
            ? "date(date_action)"
            : 'DATE(date_action)';

        $aujourd = Journal::where('date_action', '>=', $debutJour);

        $parJour = [];
        for ($i = 6; $i >= 0; $i--) {
            $jour = now()->subDays($i)->toDateString();
            $parJour[$jour] = 0;
        }

        $totaux = Journal::where('date_action', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("{$exprJour} AS jour, COUNT(*) AS total")
            ->groupByRaw($exprJour)
            ->pluck('total', 'jour');

        foreach ($totaux as $jour => $total) {
            $cle = substr((string) $jour, 0, 10);
            if (array_key_exists($cle, $parJour)) {
                $parJour[$cle] = (int) $total;
            }
        }

        $parCategorie = (clone $aujourd)
            ->selectRaw('categorie, COUNT(*) AS total')
            ->groupBy('categorie')
            ->pluck('total', 'categorie');

        return [
            'actions_aujourdhui'              => (clone $aujourd)->count(),
            'connexions_reussies_aujourdhui'  => (clone $aujourd)->where('action', 'connexion')->where('resultat', 'succes')->count(),
            'utilisateurs_distincts_aujourdhui' => (clone $aujourd)->whereNotNull('id_utilisateur')->distinct()->count('id_utilisateur'),
            'echecs_connexion_aujourdhui'     => (clone $aujourd)->where('action', 'connexion')->where('resultat', 'echec')->count(),
            'utilisateurs_actifs'             => Utilisateur::where('actif', true)->whereNotNull('mot_de_passe_defini_le')->count(),
            'comptes_desactives'              => Utilisateur::where('actif', false)->count(),
            'par_jour'                        => collect($parJour)->map(fn ($total, $jour) => [
                'jour'  => $jour,
                'total' => $total,
            ])->values(),
            'par_categorie'                   => collect($parCategorie)->map(fn ($total, $categorie) => [
                'categorie' => $categorie,
                'total'     => (int) $total,
            ])->values(),
            'echecs_recents'                  => Journal::where('action', 'connexion')
                ->where('resultat', 'echec')
                ->orderByDesc('date_action')
                ->limit(10)
                ->get(['date_action', 'compte', 'adresse_ip', 'detail'])
                ->map(fn (Journal $j) => [
                    'date_action' => $j->date_action,
                    'compte'      => $j->compte,
                    'adresse_ip'  => $j->adresse_ip,
                    'motif'       => $j->detail,
                ])->values(),
        ];
    }
}

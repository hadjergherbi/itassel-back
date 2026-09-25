<?php

namespace App\Services;

use App\Models\Historique;
use App\Models\Journal;
use App\Models\Nature;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use App\Support\Acces;
use App\Support\DoleanceFiltre;
use App\Support\Periode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class StatistiqueService
{
    private const LIBELLES_MOIS = [
        1 => 'Janv.', 2 => 'Févr.', 3 => 'Mars', 4 => 'Avr.',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juil.', 8 => 'Août',
        9 => 'Sept.', 10 => 'Oct.', 11 => 'Nov.', 12 => 'Déc.',
    ];

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

    public static function activite(string $periode = 'aujourdhui'): array
    {
        $depuis = static::debutActivite($periode);
        $driver = Schema::getConnection()->getDriverName();
        $exprJour = $driver === 'sqlite'
            ? "date(date_action)"
            : 'DATE(date_action)';

        $fenetre = Journal::where('date_action', '>=', $depuis);
        $nbJours = $periode === '30j' ? 30 : 7;

        $parJour = [];
        for ($i = $nbJours - 1; $i >= 0; $i--) {
            $parJour[now()->subDays($i)->toDateString()] = 0;
        }

        $totaux = Journal::where('date_action', '>=', now()->subDays($nbJours - 1)->startOfDay())
            ->selectRaw("{$exprJour} AS jour, COUNT(*) AS total")
            ->groupByRaw($exprJour)
            ->pluck('total', 'jour');

        foreach ($totaux as $jour => $total) {
            $cle = substr((string) $jour, 0, 10);
            if (array_key_exists($cle, $parJour)) {
                $parJour[$cle] = (int) $total;
            }
        }

        $totauxCategorie = (clone $fenetre)
            ->selectRaw('categorie, COUNT(*) AS total')
            ->groupBy('categorie')
            ->pluck('total', 'categorie');

        $categories = config('itassel.journal.categories', []);
        $comptesActifs = Utilisateur::where('actif', true)->whereNotNull('mot_de_passe_defini_le')->count();

        $echecs = static::echecsConnexion(clone $fenetre)
            ->orderByDesc('date_action')
            ->limit(10)
            ->get(['date_action', 'compte', 'adresse_ip', 'detail', 'action']);

        $ipSuspectes = static::echecsConnexion(Journal::where('date_action', '>=', $depuis))
            ->selectRaw('adresse_ip, COUNT(*) AS echecs, MAX(date_action) AS derniere_tentative')
            ->groupBy('adresse_ip')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(5)
            ->get();

        return [
            'periode'                          => $periode,
            'depuis'                           => $depuis->toDateString(),
            'actions_aujourdhui'              => (clone $fenetre)->count(),
            'connexions_reussies_aujourdhui'  => (clone $fenetre)->where('action', 'connexion')->where('resultat', 'succes')->count(),
            'utilisateurs_distincts_aujourdhui' => (clone $fenetre)->whereNotNull('id_utilisateur')->distinct()->count('id_utilisateur'),
            'echecs_connexion_aujourdhui'     => static::echecsConnexion(clone $fenetre)->count(),
            'comptes_verrouilles'             => (clone $fenetre)->where('action', 'compte_verrouille')->count(),
            'exports'                         => (clone $fenetre)->where('categorie', 'export')->count(),
            'comptes_actifs'                  => $comptesActifs,
            'utilisateurs_actifs'             => $comptesActifs,
            'comptes_desactives'              => Utilisateur::where('actif', false)->count(),
            'par_jour'                        => collect($parJour)->map(fn ($total, $jour) => [
                'jour'  => $jour,
                'total' => $total,
            ])->values(),
            'par_categorie'                   => collect($categories)->map(fn (string $categorie) => [
                'categorie' => $categorie,
                'total'     => (int) ($totauxCategorie[$categorie] ?? 0),
            ])->values(),
            'ip_suspectes'                    => $ipSuspectes->map(fn ($ligne) => [
                'adresse_ip'         => $ligne->adresse_ip,
                'echecs'             => (int) $ligne->echecs,
                'derniere_tentative' => Carbon::parse($ligne->derniere_tentative)->toIso8601String(),
            ])->values(),
            'echecs_recents'                  => $echecs->map(fn (Journal $j) => [
                'date_action' => $j->date_action,
                'compte'      => $j->compte,
                'adresse_ip'  => $j->adresse_ip,
                'motif'       => $j->detail,
                'action'      => $j->action,
            ])->values(),
        ];
    }

    private static function debutActivite(string $periode): Carbon
    {
        return match ($periode) {
            '7j'    => now()->subDays(7)->startOfDay(),
            '30j'   => now()->subDays(30)->startOfDay(),
            default => now()->startOfDay(),
        };
    }

    private static function echecsConnexion(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('action', ['connexion_echec', 'compte_verrouille'])
                ->orWhere(fn (Builder $q) => $q->where('action', 'connexion')->where('resultat', 'echec'));
        });
    }

    public static function tableauDeBord(Utilisateur $utilisateur, string $codePeriode = '6m'): array
    {
        $periode = Periode::resoudre($codePeriode);
        $visibles = Acces::doleancesVisibles($utilisateur);
        $statuts = Statut::orderBy('id_statut')->get(['id_statut', 'code', 'libelle', 'couleur']);
        $totauxParStatut = (clone $visibles)
            ->selectRaw('id_statut, COUNT(*) AS total')
            ->groupBy('id_statut')
            ->pluck('total', 'id_statut');

        $totauxParCode = [];
        foreach ($statuts as $statut) {
            $totauxParCode[$statut->code] = (int) ($totauxParStatut[$statut->id_statut] ?? 0);
        }

        $deposees = (clone $visibles)->whereBetween('date_depot', [$periode['debut'], $periode['fin']]);
        $depuis = (clone $visibles)->min('date_depot');
        $idResolue = Statut::parCode(Statut::RESOLUE)->id_statut;

        return [
            'periode'     => [
                'code'       => $periode['code'],
                'date_debut' => $periode['date_debut'],
                'date_fin'   => $periode['date_fin'],
                'libelle'    => $periode['libelle'],
            ],
            'indicateurs' => [
                'nouvelles'                  => $totauxParCode[Statut::NOUVELLE] ?? 0,
                'en_cours'                   => $totauxParCode[Statut::EN_COURS] ?? 0,
                'resolues'                   => (clone $deposees)->where('id_statut', $idResolue)->count(),
                'total'                      => (clone $deposees)->count(),
                'depuis'                     => $depuis ? substr((string) $depuis, 0, 10) : now()->toDateString(),
                'delai_moyen_jours'          => static::delaiMoyen((clone $visibles), $periode['debut'], $periode['fin']),
                'delai_moyen_tendance_jours' => static::delaiTendance(clone $visibles),
            ],
            'par_mois'            => static::parMois(clone $visibles, $periode['nb_mois']),
            'repartition'         => $statuts->map(fn (Statut $statut) => [
                'code'    => $statut->code,
                'libelle' => $statut->libelle,
                'total'   => $totauxParCode[$statut->code] ?? 0,
            ])->values(),
            'repartition_nature'  => static::repartitionNature(clone $deposees),
            'priorites'           => static::priorites(clone $visibles),
            'dernieres'           => static::dernieres(clone $visibles),
            'mes_reaffectations'  => static::mesReaffectations($utilisateur),
            'mes_reaffectations_en_attente' => Reaffectation::query()
                ->where('id_demandeur', $utilisateur->id_utilisateur)
                ->where('etat', 'en_attente')
                ->count(),
        ];
    }

    public static function parMois(Builder $query, int $nbMois = 6): array
    {
        $debut = now()->startOfMonth()->subMonths($nbMois - 1);
        $expression = Schema::getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', date_depot)"
            : "DATE_FORMAT(date_depot, '%Y-%m')";
        $moisCourant = now()->format('Y-m');

        $totaux = (clone $query)
            ->where('date_depot', '>=', $debut)
            ->selectRaw("{$expression} AS mois, COUNT(*) AS total")
            ->groupByRaw($expression)
            ->pluck('total', 'mois');

        $parMois = [];
        for ($i = 0; $i < $nbMois; $i++) {
            $mois = $debut->copy()->addMonths($i);
            $cle = $mois->format('Y-m');
            $parMois[] = [
                'mois'     => $cle,
                'libelle'  => self::LIBELLES_MOIS[(int) $mois->format('n')],
                'total'    => (int) ($totaux[$cle] ?? 0),
                'en_cours' => $cle === $moisCourant,
            ];
        }

        return $parMois;
    }

    public static function idsStatutsConclusion(): array
    {
        return Statut::query()
            ->whereIn('code', config('itassel.statuts_conclusion', []))
            ->pluck('id_statut')
            ->all();
    }

    public static function delaiMoyen(Builder $visibles, Carbon $debut, Carbon $fin): ?float
    {
        $delais = static::delaisCloturees(clone $visibles, $debut, $fin);

        if ($delais === []) {
            return null;
        }

        return round(array_sum($delais) / count($delais), 1);
    }

    public static function delaiTendance(Builder $visibles): ?float
    {
        $actuel = static::delaiMoyen(clone $visibles, now()->startOfMonth(), now()->endOfMonth());
        $precedent = static::delaiMoyen(
            clone $visibles,
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        );

        if ($actuel === null || $precedent === null) {
            return null;
        }

        return round($actuel - $precedent, 1);
    }

    /**
     * @return list<float>
     */
    private static function delaisCloturees(Builder $visibles, Carbon $debut, Carbon $fin): array
    {
        $ids = static::idsStatutsConclusion();
        if ($ids === []) {
            return [];
        }

        $doleances = (clone $visibles)->get(['id_doleance', 'date_depot']);
        if ($doleances->isEmpty()) {
            return [];
        }

        $clotures = Historique::query()
            ->whereIn('id_doleance', $doleances->pluck('id_doleance'))
            ->whereIn('id_statut_apres', $ids)
            ->selectRaw('id_doleance, MIN(date_evenement) AS date_cloture')
            ->groupBy('id_doleance')
            ->get()
            ->keyBy('id_doleance');

        $delais = [];
        foreach ($doleances as $doleance) {
            $cloture = $clotures[$doleance->id_doleance]->date_cloture ?? null;
            if (! $cloture) {
                continue;
            }
            $dateCloture = Carbon::parse($cloture);
            if ($dateCloture->lt($debut) || $dateCloture->gt($fin)) {
                continue;
            }
            $delais[] = (float) $doleance->date_depot->startOfDay()->diffInDays($dateCloture->copy()->startOfDay());
        }

        return $delais;
    }

    private static function repartitionNature(Builder $deposees): array
    {
        $totaux = (clone $deposees)
            ->selectRaw('id_nature, COUNT(*) AS total')
            ->groupBy('id_nature')
            ->pluck('total', 'id_nature');

        return Nature::query()
            ->whereIn('id_nature', $totaux->keys())
            ->get(['id_nature', 'libelle'])
            ->map(fn (Nature $nature) => [
                'id_nature' => $nature->id_nature,
                'libelle'   => $nature->libelle,
                'total'     => (int) ($totaux[$nature->id_nature] ?? 0),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    public static function priorites(Builder $visibles): array
    {
        $nouvelleJours = (int) config('itassel.priorites.nouvelle_jours', 5);
        $informationJours = (int) config('itassel.priorites.information_jours', 15);
        $idNouvelle = Statut::parCode(Statut::NOUVELLE)->id_statut;

        return [
            'complements_a_examiner' => (clone $visibles)
                ->whereHas('complements', fn ($q) => $q->where('etat', 'recu'))
                ->count(),
            'nouvelles_en_retard' => (clone $visibles)
                ->where('id_statut', $idNouvelle)
                ->where('date_depot', '<', now()->subDays($nouvelleJours))
                ->count(),
            'informations_sans_reponse' => DoleanceFiltre::appliquerInformationsSansReponse(clone $visibles)->count(),
            'seuils' => [
                'nouvelle_jours'    => $nouvelleJours,
                'information_jours' => $informationJours,
            ],
        ];
    }

    public static function dernieres(Builder $visibles): array
    {
        $orange = (int) config('itassel.anciennete.orange', 5);
        $rouge = (int) config('itassel.anciennete.rouge', 10);

        return (clone $visibles)
            ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
            ->with([
                'statut:id_statut,code,libelle,couleur',
                'nature:id_nature,libelle',
            ])
            ->orderBy('date_depot')
            ->limit(5)
            ->get(['id_doleance', 'reference', 'nom', 'prenom', 'date_depot', 'id_statut', 'id_nature'])
            ->map(function ($doleance) use ($orange, $rouge) {
                $age = (int) $doleance->date_depot->startOfDay()->diffInDays(now()->startOfDay());
                $niveau = $age >= $rouge ? 'rouge' : ($age >= $orange ? 'orange' : 'normal');

                return [
                    'reference'  => $doleance->reference,
                    'nom'        => $doleance->nom,
                    'prenom'     => $doleance->prenom,
                    'nature'     => $doleance->nature?->libelle,
                    'date_depot' => $doleance->date_depot,
                    'statut'     => $doleance->statut?->versApi(),
                    'age_jours'  => $age,
                    'niveau_age' => $niveau,
                ];
            })->values()->all();
    }

    public static function mesReaffectations(Utilisateur $utilisateur): array
    {
        return Reaffectation::query()
            ->where('id_demandeur', $utilisateur->id_utilisateur)
            ->with([
                'doleance:id_doleance,reference',
                'servicePropose:id_service,nom_service',
                'serviceDestination:id_service,nom_service',
            ])
            ->orderByDesc('date_demande')
            ->limit(3)
            ->get()
            ->map(function (Reaffectation $demande) {
                $motifRefus = null;
                if ($demande->etat === 'refusee') {
                    $motifRefus = Historique::query()
                        ->where('id_doleance', $demande->id_doleance)
                        ->where('type_evenement', 'reaffectation_refusee')
                        ->orderByDesc('date_evenement')
                        ->value('detail');
                }

                return [
                    'id_reaffectation'     => $demande->id_reaffectation,
                    'reference'            => $demande->doleance?->reference,
                    'service_propose'      => $demande->servicePropose?->nom_service,
                    'service_destination'  => $demande->serviceDestination?->nom_service,
                    'etat'                 => $demande->etat,
                    'motif'                => $demande->motif,
                    'motif_refus'          => $motifRefus,
                    'date_demande'         => $demande->date_demande,
                    'date_decision'        => $demande->date_decision,
                ];
            })->values()->all();
    }
}

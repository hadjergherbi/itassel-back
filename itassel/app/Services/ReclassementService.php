<?php

namespace App\Services;

use App\Exceptions\ConflitMetier;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReclassementService
{
    public static function estAReclasser(Doleance $doleance): bool
    {
        $doleance->loadMissing(['statut', 'nature']);
        $code = $doleance->statut?->code;

        if (in_array($code, [Statut::NON_FONDEE, Statut::CLOTUREE], true)) {
            return true;
        }

        return $code === Statut::RESOLUE && $doleance->nature?->famille === 'demande';
    }

    public static function ciblesPossibles(Doleance $doleance)
    {
        $doleance->loadMissing(['statut', 'nature']);
        $codes = static::codesCibles($doleance);

        if ($codes === []) {
            return Statut::query()->whereRaw('1 = 0')->get();
        }

        $famille = $doleance->nature?->famille ?? 'reclamation';

        return Statut::whereIn('code', $codes)
            ->where('selectionnable', true)
            ->get()
            ->filter(function (Statut $statut) use ($famille) {
                $familles = config("itassel.issues.{$statut->code}.familles", ['reclamation', 'demande']);

                return in_array($famille, $familles, true);
            })
            ->values();
    }

    public static function reclasser(Doleance $doleance, Statut $cible, Utilisateur $acteur, string $motif): Doleance
    {
        if (! static::estAReclasser($doleance)) {
            throw new ConflitMetier('reclassement_interdit', 'Ce dossier n\'est pas à reclasser.');
        }

        $codes = static::codesCibles($doleance);
        if (! in_array($cible->code, $codes, true)) {
            throw new RuntimeException('Cette issue n\'est pas autorisée pour le reclassement.');
        }

        $famille = $doleance->nature?->famille ?? 'reclamation';
        $familles = config("itassel.issues.{$cible->code}.familles", []);
        if ($familles && ! in_array($famille, $familles, true)) {
            throw new RuntimeException('Cette issue n\'est pas autorisée pour la famille de la nature.');
        }

        return DB::transaction(function () use ($doleance, $cible, $acteur, $motif) {
            $avant = $doleance->id_statut;
            $doleance->update(['id_statut' => $cible->id_statut]);

            Historique::create([
                'date_evenement' => now(),
                'type_evenement' => 'reclassement',
                'detail' => $motif,
                'visible_demandeur' => false,
                'id_doleance' => $doleance->id_doleance,
                'id_utilisateur' => $acteur->id_utilisateur,
                'id_statut_avant' => $avant,
                'id_statut_apres' => $cible->id_statut,
            ]);

            return $doleance->fresh(['statut', 'nature']);
        });
    }

    private static function codesCibles(Doleance $doleance): array
    {
        $code = $doleance->statut?->code;
        $carte = config('itassel.reclassement', []);

        if ($code === Statut::RESOLUE && $doleance->nature?->famille === 'demande') {
            return $carte['resolue'] ?? ['reponse_apportee'];
        }

        if ($code === Statut::CLOTUREE) {
            $conclusion = Historique::where('id_doleance', $doleance->id_doleance)
                ->whereNotNull('id_statut_apres')
                ->whereHas('statutApres', fn ($q) => $q->whereIn('code', Statut::codesIssues()))
                ->latest('date_evenement')
                ->first();

            $suggere = $conclusion?->statutApres?->code;
            $famille = $doleance->nature?->famille ?? 'reclamation';

            return collect(Statut::codesIssues())
                ->filter(function (string $issue) use ($famille, $suggere) {
                    $familles = config("itassel.issues.{$issue}.familles", []);

                    return in_array($famille, $familles, true) && ($suggere === null || $issue === $suggere);
                })
                ->values()
                ->all();
        }

        return $carte[$code] ?? [];
    }
}

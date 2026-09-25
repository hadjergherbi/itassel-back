<?php

namespace App\Support;

use App\Models\Statut;
use App\Models\Utilisateur;
use App\Services\ReclassementService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DoleanceFiltre
{
    public static function valider(Request $request, bool $avecPage = true): array
    {
        $regles = [
            'statut'            => ['nullable'],
            'service'           => ['nullable', 'integer'],
            'q'                 => ['nullable', 'string', 'max:100'],
            'nature'            => ['nullable', 'integer', 'exists:natures,id_nature'],
            'natures'           => ['nullable', 'array'],
            'natures.*'         => ['integer', 'exists:natures,id_nature'],
            'periode'           => ['nullable', 'string', 'in:7j,30j,3m,6m,annee'],
            'a_examiner'        => ['nullable', 'integer', 'in:0,1'],
            'age_min'           => ['nullable', 'integer', 'min:1'],
            'info_sans_reponse' => ['nullable', 'integer', 'in:0,1'],
            'reaffectation'     => ['nullable', 'string', 'in:en_attente'],
            'sans_responsable'  => ['nullable', 'integer', 'in:0,1'],
            'issue'             => ['nullable', 'string'],
            'a_reclasser'       => ['nullable', 'integer', 'in:0,1'],
            'date_debut'        => ['nullable', 'date'],
            'date_fin'          => ['nullable', 'date'],
            'tri'               => ['nullable', 'string', 'in:date_depot,reference'],
            'sens'              => ['nullable', 'string', 'in:asc,desc'],
            'par_page'          => ['nullable', 'integer', 'min:1', 'max:100'],
            'format'            => ['nullable', 'string', 'in:csv,pdf'],
            'graphiques'        => ['nullable', 'boolean'],
        ];

        if ($avecPage) {
            $regles['page'] = ['nullable', 'integer', 'min:1'];
        }

        $data = $request->validate($regles, [
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure à la date de début.',
        ]);

        if (! empty($data['date_debut']) && ! empty($data['date_fin'])) {
            $debut = Carbon::parse($data['date_debut'])->startOfDay();
            $fin = Carbon::parse($data['date_fin'])->startOfDay();

            if ($fin->lt($debut)) {
                throw ValidationException::withMessages([
                    'date_fin' => ['La date de fin doit être postérieure à la date de début.'],
                ]);
            }

            if ($debut->copy()->addMonths(12)->lt($fin)) {
                throw ValidationException::withMessages([
                    'date_fin' => ['La période ne peut pas dépasser 12 mois.'],
                ]);
            }
        }

        if (isset($data['statut'])) {
            $data['statut'] = is_array($data['statut'])
                ? array_map('intval', $data['statut'])
                : (int) $data['statut'];
        }

        if ($request->exists('graphiques')) {
            $data['graphiques'] = $request->boolean('graphiques');
        }

        return $data;
    }

    public static function appliquer(Builder $query, Utilisateur $utilisateur, array $filtres): Builder
    {
        $super = $utilisateur->estSuperAdmin();

        if (! empty($filtres['service']) && $super) {
            $query->where('id_service', $filtres['service']);
        }

        if (! empty($filtres['q'])) {
            $texte = '%'.addcslashes($filtres['q'], '%_').'%';
            $query->where(function ($w) use ($texte) {
                $w->where('reference', 'like', $texte)
                  ->orWhere('nom', 'like', $texte)
                  ->orWhere('prenom', 'like', $texte)
                  ->orWhere('objet', 'like', $texte);
            });
        }

        $natures = array_values(array_filter(array_merge(
            isset($filtres['natures']) ? (array) $filtres['natures'] : [],
            ! empty($filtres['nature']) ? [(int) $filtres['nature']] : [],
        )));

        if ($natures !== []) {
            $query->whereIn('id_nature', $natures);
        }

        if (! empty($filtres['periode'])) {
            $depuis = match ($filtres['periode']) {
                '7j'    => now()->subDays(7),
                '30j'   => now()->subDays(30),
                '3m'    => now()->subMonths(3),
                '6m'    => now()->subMonths(6),
                'annee' => now()->startOfYear(),
            };
            $query->where('date_depot', '>=', $depuis);
        }

        if (! empty($filtres['date_debut'])) {
            $query->whereDate('date_depot', '>=', $filtres['date_debut']);
        }

        if (! empty($filtres['date_fin'])) {
            $query->whereDate('date_depot', '<=', $filtres['date_fin']);
        }

        if (! empty($filtres['age_min'])) {
            $query->where('date_depot', '<=', now()->subDays((int) $filtres['age_min']));
        }

        if (! empty($filtres['info_sans_reponse'])) {
            static::appliquerInformationsSansReponse($query);
        }

        if ($super && ! empty($filtres['sans_responsable'])) {
            static::appliquerSansResponsable($query);
        }

        if ($super && ! empty($filtres['a_reclasser'])) {
            static::appliquerAReclasser($query);
        }

        if (! empty($filtres['issue'])) {
            $query->whereHas('statut', fn ($q) => $q->where('code', $filtres['issue']));
        }

        return $query;
    }

    public static function appliquerInformationsSansReponse(Builder $query): Builder
    {
        $idInfo = Statut::parCode(Statut::INFORMATION_DEMANDEE)->id_statut;
        $seuil = now()->subDays((int) config('itassel.priorites.information_jours', 15));

        return $query
            ->where('id_statut', $idInfo)
            ->whereIn('id_doleance', function ($sous) use ($idInfo, $seuil) {
                $sous->select('id_doleance')
                    ->from('historiques')
                    ->where('id_statut_apres', $idInfo)
                    ->groupBy('id_doleance')
                    ->havingRaw('MAX(date_evenement) < ?', [$seuil]);
            });
    }

    public static function appliquerStatut(Builder $query, array $filtres): Builder
    {
        if (empty($filtres['statut'])) {
            return $query;
        }

        $statuts = is_array($filtres['statut']) ? $filtres['statut'] : [$filtres['statut']];

        return $query->whereIn('id_statut', $statuts);
    }

    public static function appliquerSansResponsable(Builder $query): Builder
    {
        return $query
            ->whereHas('service', fn ($q) => $q->whereNull('id_responsable'))
            ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()));
    }

    public static function appliquerAReclasser(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereHas('statut', fn ($s) => $s->whereIn('code', [Statut::NON_FONDEE, Statut::CLOTUREE]))
              ->orWhere(function ($r) {
                  $r->whereHas('statut', fn ($s) => $s->where('code', Statut::RESOLUE))
                    ->whereHas('nature', fn ($n) => $n->where('famille', 'demande'));
              });
        });
    }

    public static function estAReclasser($doleance): bool
    {
        return ReclassementService::estAReclasser($doleance);
    }
}

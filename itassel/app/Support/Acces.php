<?php

namespace App\Support;

use App\Models\Doleance;
use App\Models\Nature;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class Acces
{
    /**
     * Doléances qu'un utilisateur a le droit de voir :
     * - Super administrateur : toutes ;
     * - administrateur de service : celles de son service, plus celles
     *   dont la nature est « Toutes natures », plus celles rattachées
     *   au domaine « Tous les domaines » (visibles de tous les services).
     */
    public static function doleancesVisibles(Utilisateur $utilisateur): Builder
    {
        $query = Doleance::query();

        if (! $utilisateur->estSuperAdmin()) {
            $idService = $utilisateur->id_service ?? 0;
            $query->where(function (Builder $q) use ($idService) {
                $q->where('id_service', $idService)
                    ->orWhereHas('nature', function (Builder $n) {
                        $n->whereRaw('LOWER(TRIM(libelle)) = ?', [mb_strtolower(Nature::TOUTES_NATURES)]);
                    })
                    ->orWhereHas('service', function (Builder $s) {
                        $s->whereRaw('LOWER(TRIM(nom_service)) = ?', [mb_strtolower(Service::TOUS_LES_DOMAINES)]);
                    });
            });
        }

        return $query;
    }

    public static function reponseDossierReaffecte(Utilisateur $utilisateur, string $reference): ?JsonResponse
    {
        $doleance = Doleance::with('service')->where('reference', strtoupper($reference))->first();
        if (! $doleance) {
            return null;
        }

        $acceptee = Reaffectation::where('id_doleance', $doleance->id_doleance)
            ->where('id_demandeur', $utilisateur->id_utilisateur)
            ->where('etat', 'acceptee')
            ->latest('date_decision')
            ->first();

        if (! $acceptee) {
            return null;
        }

        return response()->json([
            'code'      => 'dossier_reaffecte',
            'message'   => 'Ce dossier a été réaffecté et n\'est plus visible depuis votre service.',
            'reference' => $doleance->reference,
            'service'   => $doleance->service?->nom_service,
        ], 403);
    }
}

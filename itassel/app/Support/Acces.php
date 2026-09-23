<?php

namespace App\Support;

use App\Models\Doleance;
use App\Models\Reaffectation;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class Acces
{
    /**
     * Doléances qu'un utilisateur a le droit de voir :
     * - Super administrateur : toutes ;
     * - administrateur de service : uniquement celles de son service.
     */
    public static function doleancesVisibles(Utilisateur $utilisateur): Builder
    {
        $query = Doleance::query();

        if (! $utilisateur->estSuperAdmin()) {
            // Un administrateur sans service ne voit rien.
            $query->where('id_service', $utilisateur->id_service ?? 0);
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

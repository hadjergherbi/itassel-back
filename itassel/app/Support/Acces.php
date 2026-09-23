<?php

namespace App\Support;

use App\Models\Doleance;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;

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
}

<?php

namespace App\Policies;

use App\Models\Complement;
use App\Models\Utilisateur;
use App\Support\Acces;

class ComplementPolicy
{
    public function annuler(Utilisateur $acteur, Complement $complement): bool
    {
        if (! $acteur->peut('complements.annuler')) {
            return false;
        }

        return Acces::doleancesVisibles($acteur)
            ->whereKey($complement->id_doleance)
            ->exists();
    }
}

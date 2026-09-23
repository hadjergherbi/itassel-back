<?php

namespace App\Policies;

use App\Models\Utilisateur;

class UtilisateurPolicy
{
    public function gerer(Utilisateur $acteur): bool
    {
        return $acteur->peut('utilisateurs.gerer');
    }

    public function view(Utilisateur $acteur, Utilisateur $cible): bool
    {
        return $acteur->peut('utilisateurs.gerer');
    }

    public function update(Utilisateur $acteur, Utilisateur $cible): bool
    {
        return $acteur->peut('utilisateurs.gerer');
    }

    public function delete(Utilisateur $acteur, Utilisateur $cible): bool
    {
        return $acteur->peut('utilisateurs.gerer');
    }
}

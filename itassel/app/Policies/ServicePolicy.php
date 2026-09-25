<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\Utilisateur;

class ServicePolicy
{
    public function gerer(Utilisateur $acteur): bool
    {
        return $acteur->peut('services.gerer');
    }

    public function update(Utilisateur $acteur, Service $service): bool
    {
        return $acteur->peut('services.gerer');
    }

    public function delete(Utilisateur $acteur, Service $service): bool
    {
        return $acteur->estSuperAdmin() && $acteur->peut('services.gerer');
    }
}

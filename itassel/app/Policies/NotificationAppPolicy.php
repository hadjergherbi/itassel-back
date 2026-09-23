<?php

namespace App\Policies;

use App\Models\NotificationApp;
use App\Models\Utilisateur;

class NotificationAppPolicy
{
    public function view(Utilisateur $acteur, NotificationApp $notification): bool
    {
        return (int) $notification->id_utilisateur === (int) $acteur->id_utilisateur;
    }

    public function update(Utilisateur $acteur, NotificationApp $notification): bool
    {
        return $this->view($acteur, $notification);
    }
}

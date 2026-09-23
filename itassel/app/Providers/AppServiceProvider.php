<?php

namespace App\Providers;

use App\Models\Complement;
use App\Models\NotificationApp;
use App\Models\Service;
use App\Models\Utilisateur;
use App\Policies\ComplementPolicy;
use App\Policies\NotificationAppPolicy;
use App\Policies\ServicePolicy;
use App\Policies\UtilisateurPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Utilisateur::class, UtilisateurPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Complement::class, ComplementPolicy::class);
        Gate::policy(NotificationApp::class, NotificationAppPolicy::class);
    }
}

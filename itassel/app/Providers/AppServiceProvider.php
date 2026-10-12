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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->limiteur('referentiels', 60);
        $this->limiteur('formulaire-jeton', 30);
        $this->limiteur('formulaire-depot', 10);
        $this->limiteur('suivi-code', 20);
        $this->limiteur('suivi-dossier', 20);
        $this->limiteur('login', 5);
        $this->limiteur('mot-de-passe', 5);
        $this->limiteur('changement-mot-de-passe', 5);
        $this->limiteur('mentions', 60);
        $this->limiteur('pieces-apercu', 60);
    }

    /**
     * Chaque limiteur a son propre seau : le nom entre dans la clé.
     * Les routes authentifiées sont comptées par utilisateur, les autres par IP.
     */
    private function limiteur(string $nom, int $parMinute): void
    {
        RateLimiter::for($nom, function (Request $request) use ($nom, $parMinute) {
            $identite = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute($parMinute)->by($nom.'|'.$identite);
        });
    }
}

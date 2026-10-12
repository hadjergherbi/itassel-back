<?php

namespace App\Console\Commands;

use App\Models\Utilisateur;
use Illuminate\Console\Command;
use Throwable;

class VerifierProduction extends Command
{
    protected $signature = 'itassel:verifier-production';

    protected $description = 'Contrôle la configuration avant une mise en production (code de sortie non nul si un point est KO).';

    public function handle(): int
    {
        $lignes = [
            $this->ligne(
                'APP_ENV différent de local',
                config('app.env') !== 'local',
                'APP_ENV=local est interdit pour une mise en production.',
            ),
            $this->ligne(
                'APP_DEBUG désactivé en production',
                config('app.env') !== 'production' || ! config('app.debug'),
                'APP_ENV=production exige APP_DEBUG=false.',
            ),
            $this->ligne(
                'APP_URL en HTTPS',
                str_starts_with((string) config('app.url'), 'https://'),
                (string) config('app.url'),
            ),
            $this->ligne(
                'SANCTUM_EXPIRATION ≤ 480',
                (int) config('sanctum.expiration') <= 480,
                (string) config('sanctum.expiration'),
            ),
            $this->ligne(
                'CORS sans * ni localhost',
                $this->corsAcceptable(implode(',', (array) config('cors.allowed_origins'))),
                implode(',', (array) config('cors.allowed_origins')),
            ),
            $this->ligne(
                'MAIL_MAILER réel',
                ! in_array((string) config('mail.default'), ['log', 'array'], true),
                (string) config('mail.default'),
            ),
            $this->ligne(
                'Mot de passe démo si comptes démo présents',
                $this->motDePasseDemoRenseigne(),
                'ITASSEL_DEMO_PASSWORD',
            ),
        ];

        $this->table(['Résultat', 'Contrôle', 'Détail'], $lignes);

        $ko = collect($lignes)->contains(fn (array $ligne) => $ligne[0] === 'KO');

        return $ko ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function ligne(string $controle, bool $ok, string $detail): array
    {
        return [$ok ? 'OK' : 'KO', $controle, $detail];
    }

    private function corsAcceptable(string $origines): bool
    {
        $valeur = mb_strtolower($origines);
        if ($valeur === '' || str_contains($valeur, '*') || str_contains($valeur, 'localhost') || str_contains($valeur, '127.0.0.1')) {
            return false;
        }

        return true;
    }

    private function motDePasseDemoRenseigne(): bool
    {
        $emails = array_values(array_filter((array) config('itassel.demo_emails', [])));
        if ($emails === []) {
            return true;
        }

        try {
            $presents = Utilisateur::query()->whereIn('email', $emails)->exists();
        } catch (Throwable) {
            return true;
        }

        if (! $presents) {
            return true;
        }

        return is_string(config('itassel.demo_password')) && config('itassel.demo_password') !== '';
    }
}

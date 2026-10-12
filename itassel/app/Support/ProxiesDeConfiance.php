<?php

namespace App\Support;

class ProxiesDeConfiance
{
    /**
     * @return array<int, string>|string|null
     */
    public static function depuis(mixed $valeur): array|string|null
    {
        if (! is_string($valeur)) {
            return null;
        }

        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }

        if ($valeur === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $valeur)), fn (string $ip) => $ip !== ''));
    }
}

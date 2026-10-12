<?php

namespace App\Support;

use Normalizer;

class Libelle
{
    /**
     * Comparaison trim, insensible à la casse et aux accents.
     */
    public static function equivalents(?string $a, ?string $b): bool
    {
        return self::normaliser($a) === self::normaliser($b);
    }

    public static function normaliser(?string $valeur): string
    {
        $valeur = trim((string) $valeur);
        $valeur = mb_strtolower($valeur);

        if (class_exists(Normalizer::class)) {
            $decompose = Normalizer::normalize($valeur, Normalizer::FORM_D);
            if (is_string($decompose)) {
                $valeur = preg_replace('/\p{M}+/u', '', $decompose) ?? $decompose;
            }
        }

        return $valeur;
    }
}

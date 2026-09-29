<?php

namespace App\Support;

use Carbon\Carbon;

class Periode
{
    public const CODES_TABLEAU = ['30j', '3m', '6m', 'annee'];

    public static function resoudre(string $code = '6m'): array
    {
        $code = in_array($code, self::CODES_TABLEAU, true) ? $code : '6m';
        $fin = now()->endOfDay();

        [$debut, $libelle] = match ($code) {
            '30j' => [now()->subDays(30)->startOfDay(), 'Sur 30 jours'],
            '3m' => [now()->subMonths(3)->startOfDay(), 'Sur 3 mois'],
            '6m' => [now()->subMonths(6)->startOfDay(), 'Sur 6 mois'],
            'annee' => [now()->copy()->startOfYear(), 'Depuis janvier'],
        };

        return [
            'code' => $code,
            'date_debut' => $debut->toDateString(),
            'date_fin' => $fin->toDateString(),
            'libelle' => $libelle,
            'debut' => $debut,
            'fin' => $fin,
            'nb_mois' => $code === 'annee' ? 12 : 6,
        ];
    }

    public static function depuisFiltre(array $filtres): array
    {
        if (! empty($filtres['date_debut']) && ! empty($filtres['date_fin'])) {
            return [
                Carbon::parse($filtres['date_debut'])->toDateString(),
                Carbon::parse($filtres['date_fin'])->toDateString(),
            ];
        }

        $periode = self::resoudre($filtres['periode'] ?? '6m');

        return [$periode['date_debut'], $periode['date_fin']];
    }
}

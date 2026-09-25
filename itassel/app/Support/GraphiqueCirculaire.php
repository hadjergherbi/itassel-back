<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class GraphiqueCirculaire
{
    public const PALETTE = [
        '#006b3f',
        '#1a5f9e',
        '#d97706',
        '#7c3aed',
        '#0f766e',
        '#b42318',
        '#6b7280',
    ];

    /**
     * Anneau PNG en data URI. Null si le total est nul ou si GD est absent.
     *
     * @param  list<array{libelle?: string, valeur?: int, couleur?: string|null}>  $parts
     */
    public static function anneau(array $parts, int $taille = 220): ?string
    {
        $serie = static::serie($parts);
        $total = array_sum(array_column($serie, 'valeur'));

        if ($total <= 0) {
            return null;
        }

        if (! extension_loaded('gd')) {
            Log::warning('Extension GD absente : le graphique circulaire n\'a pas été généré.');

            return null;
        }

        $echelle = 2;
        $cote = max(2, $taille) * $echelle;
        $image = imagecreatetruecolor($cote, $cote);
        $blanc = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $cote, $cote, $blanc);

        $cumul = 0;
        $depart = -90.0;
        $centre = (int) ($cote / 2);
        $diametre = $cote - 8;

        foreach ($serie as $part) {
            $cumul += $part['valeur'];
            $fin = -90.0 + ($cumul / $total) * 360.0;
            [$r, $g, $b] = static::rgb($part['couleur']);
            $couleur = imagecolorallocate($image, $r, $g, $b);
            imagefilledarc(
                $image,
                $centre,
                $centre,
                $diametre,
                $diametre,
                (int) round($depart),
                (int) round($fin),
                $couleur,
                IMG_ARC_PIE
            );
            $depart = $fin;
        }

        $trou = (int) round($cote * 0.6);
        imagefilledellipse($image, $centre, $centre, $trou, $trou, $blanc);

        $finale = imagecreatetruecolor($taille, $taille);
        $fond = imagecolorallocate($finale, 255, 255, 255);
        imagefilledrectangle($finale, 0, 0, $taille, $taille, $fond);
        imagecopyresampled($finale, $image, 0, 0, 0, 0, $taille, $taille, $cote, $cote);
        imagedestroy($image);

        ob_start();
        imagepng($finale);
        $binaire = ob_get_clean();
        imagedestroy($finale);

        if (! is_string($binaire) || $binaire === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binaire);
    }

    /**
     * Parts à valeur nulle retirées. Au-delà de 6 parts, le reste est regroupé dans « Autres ».
     *
     * @param  list<array{libelle?: string, valeur?: int, couleur?: string|null}>  $parts
     * @return list<array{libelle: string, valeur: int, couleur: string}>
     */
    public static function serie(array $parts): array
    {
        $propres = [];
        foreach ($parts as $part) {
            $valeur = (int) ($part['valeur'] ?? 0);
            if ($valeur <= 0) {
                continue;
            }
            $propres[] = [
                'libelle' => (string) ($part['libelle'] ?? ''),
                'valeur'  => $valeur,
                'couleur' => isset($part['couleur']) && $part['couleur'] !== '' ? (string) $part['couleur'] : null,
            ];
        }

        usort($propres, fn (array $a, array $b) => $b['valeur'] <=> $a['valeur']);

        if (count($propres) > 6) {
            $reste = array_slice($propres, 5);
            $propres = array_slice($propres, 0, 5);
            $propres[] = [
                'libelle' => 'Autres',
                'valeur'  => array_sum(array_column($reste, 'valeur')),
                'couleur' => self::PALETTE[6],
            ];
        }

        foreach ($propres as $index => &$part) {
            $part['couleur'] = $part['couleur'] ?: self::PALETTE[$index % count(self::PALETTE)];
        }
        unset($part);

        return $propres;
    }

    /**
     * @param  list<array{libelle?: string, valeur?: int, couleur?: string|null}>  $parts
     * @return list<array{libelle: string, valeur: int, pourcentage: float, couleur: string}>
     */
    public static function legende(array $parts): array
    {
        $serie = static::serie($parts);
        $pourcentages = static::pourcentages(array_column($serie, 'valeur'));

        $lignes = [];
        foreach ($serie as $index => $part) {
            $lignes[] = [
                'libelle'      => $part['libelle'],
                'valeur'       => $part['valeur'],
                'pourcentage'  => $pourcentages[$index] ?? 0.0,
                'couleur'      => $part['couleur'],
            ];
        }

        return $lignes;
    }

    /**
     * @param  list<int>  $valeurs
     * @return list<float>
     */
    public static function pourcentages(array $valeurs): array
    {
        $total = array_sum($valeurs);
        $nombre = count($valeurs);
        if ($nombre === 0) {
            return [];
        }
        if ($total <= 0) {
            return array_fill(0, $nombre, 0.0);
        }

        $resultat = [];
        $somme = 0.0;
        $dernier = $nombre - 1;

        foreach ($valeurs as $index => $valeur) {
            if ($index === $dernier) {
                $resultat[] = round(100 - $somme, 1);

                continue;
            }
            $part = round(((int) $valeur) / $total * 100, 1);
            $somme += $part;
            $resultat[] = $part;
        }

        return $resultat;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $hex = str_pad(substr($hex, 0, 6), 6, '0');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}

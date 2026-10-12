<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;
use InvalidArgumentException;

class ExportPdf
{
    /**
     * Le pied de page (numéro de page) est dessiné par un script text/php dans
     * resources/views/exports/_layout.blade.php. Cette version de DomPDF
     * n'implémente pas counter(page), donc l'interpréteur PHP du moteur reste
     * actif. Il ne s'applique qu'aux vues internes exports.* : le contenu
     * saisi par les utilisateurs y est échappé par Blade, et aucun HTML
     * extérieur n'est chargé (remote désactivé, chroot limité).
     */
    public static function depuisVue(string $vue, array $donnees, string $format = 'a4', string $orientation = 'landscape'): PDF
    {
        if (! str_starts_with($vue, 'exports.')) {
            throw new InvalidArgumentException('Seules les vues internes d\'export peuvent être rendues en PDF.');
        }

        $pdf = PdfFacade::loadView($vue, $donnees)->setPaper($format, $orientation);
        self::preparer($pdf);

        return $pdf;
    }

    public static function preparer(PDF $pdf): void
    {
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $options->setIsRemoteEnabled(false);
        $options->setChroot([public_path(), storage_path('fonts')]);
    }

    public static function logo(): ?string
    {
        foreach (['logo.png', 'logo.jpg', 'logo.jpeg', 'images/logo.png', 'img/logo.png'] as $fichier) {
            $chemin = public_path($fichier);
            if (is_file($chemin)) {
                return str_replace('\\', '/', $chemin);
            }
        }

        return null;
    }

    public static function police(string $fichier): string
    {
        $chemin = str_replace('\\', '/', storage_path('fonts/'.$fichier));
        if (! str_starts_with($chemin, '/')) {
            $chemin = '/'.$chemin;
        }

        return 'file://'.$chemin;
    }
}

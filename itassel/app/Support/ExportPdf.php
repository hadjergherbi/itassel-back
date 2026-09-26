<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

class ExportPdf
{
    public static function preparer(PDF $pdf): void
    {
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $options->setIsRemoteEnabled(true);
        $options->setChroot([base_path(), public_path(), storage_path()]);
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

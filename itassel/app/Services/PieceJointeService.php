<?php

namespace App\Services;

use App\Models\Doleance;
use App\Models\PieceJointe;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class PieceJointeService
{
    /**
     * Ré-encode JPEG/PNG via GD pour retirer EXIF (GPS). Refuse si GD manque ou si le décodage échoue.
     */
    public static function reencoderImageSansExif(UploadedFile $fichier, string $extension): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $chemin = $fichier->getRealPath();
        if (! is_string($chemin) || $chemin === '' || ! is_file($chemin)) {
            return false;
        }

        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($chemin),
            'png'         => @imagecreatefrompng($chemin),
            default       => false,
        };

        if ($image === false) {
            return false;
        }

        $ok = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $chemin, 90),
            'png'         => imagepng($image, $chemin),
            default       => false,
        };

        imagedestroy($image);

        return $ok !== false;
    }

    /**
     * Ré-encode les images JPG/PNG sans EXIF, stocke le fichier et crée la ligne PieceJointe.
     *
     * @throws ValidationException si le ré-encodage d'une image échoue
     */
    public static function enregistrer(
        UploadedFile $fichier,
        Doleance $doleance,
        string $origine,
        ?int $idComplement = null,
    ): PieceJointe {
        $extension = strtolower($fichier->getClientOriginalExtension());

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)
            && ! self::reencoderImageSansExif($fichier, $extension)) {
            throw ValidationException::withMessages([
                'piece_jointe' => ['Image invalide.'],
            ]);
        }

        $chemin = $fichier->store('pieces-jointes/'.$doleance->id_doleance, 'local');

        return PieceJointe::create([
            'nom_fichier'   => $fichier->getClientOriginalName(),
            'type'          => $extension === 'jpeg' ? 'jpg' : $extension,
            'taille'        => filesize($fichier->getRealPath()) ?: $fichier->getSize(),
            'chemin'        => $chemin,
            'origine'       => $origine,
            'id_doleance'   => $doleance->id_doleance,
            'id_complement' => $idComplement,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PieceJointe;
use App\Services\JournalService;
use App\Support\Acces;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class PieceJointeController extends Controller
{
    /**
     * GET /api/admin/pieces-jointes/{id}/telecharger
     * Seulement si la doléance de la pièce est visible par l'utilisateur.
     */
    public function telecharger(Request $request, int $id)
    {
        $piece = $this->pieceAccessible($request, $id);
        if ($piece instanceof JsonResponse) {
            return $piece;
        }

        $nom = $this->nomFichierAssaini((string) $piece->nom_fichier);

        return Storage::disk('local')->download($piece->chemin, $nom, [
            'Content-Type'              => $this->typeMime($piece->type),
            'Content-Disposition'       => 'attachment; filename="'.$nom.'"',
            'X-Content-Type-Options'    => 'nosniff',
        ]);
    }

    /**
     * GET /api/admin/pieces-jointes/{id}/apercu
     * Affichage dans l'application (inline), sans forcer le téléchargement.
     */
    public function apercu(Request $request, int $id): Response
    {
        $piece = $this->pieceAccessible($request, $id);
        if ($piece instanceof JsonResponse) {
            return $piece;
        }

        $mime = $this->typeMimeApercu($piece->type);
        if ($mime === null) {
            return response()->json([
                'message' => 'Aperçu indisponible pour ce type de fichier.',
            ], 415);
        }

        $this->journaliserApercu($request, $piece);

        $nom = $this->nomFichierAssaini((string) $piece->nom_fichier);
        $ascii = $this->nomFichierAscii($nom);

        return Storage::disk('local')->response($piece->chemin, $nom, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $nom,
                $ascii
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ], 'inline');
    }

    /**
     * Pièce existante, doléance visible, fichier présent sur le disque privé.
     */
    private function pieceAccessible(Request $request, int $id): PieceJointe|JsonResponse
    {
        $piece = PieceJointe::find($id);

        $autorise = $piece && Acces::doleancesVisibles($request->user())
            ->whereKey($piece->id_doleance)
            ->exists();

        if (! $autorise) {
            return response()->json(['message' => 'Pièce jointe introuvable.'], 404);
        }

        if (! Storage::disk('local')->exists($piece->chemin)) {
            return response()->json(['message' => 'Le fichier est absent du serveur.'], 404);
        }

        return $piece;
    }

    private function journaliserApercu(Request $request, PieceJointe $piece): void
    {
        $utilisateur = $request->user();
        $cle = 'consultation_piece_jointe:'.$utilisateur->id_utilisateur.':'.$piece->id_piece;

        if (! Cache::add($cle, true, now()->addMinutes(10))) {
            return;
        }

        JournalService::action(
            $request,
            $utilisateur,
            'consultation_piece_jointe',
            $piece->nom_fichier,
            $piece->doleance,
        );
    }

    private function nomFichierAssaini(string $nom): string
    {
        $nom = str_replace(["\0", '\\', '/'], '', $nom);
        $nom = basename($nom);
        $nom = preg_replace('/[^\p{L}\p{N}._ ()-]+/u', '_', $nom) ?? '';
        $nom = trim($nom, '._ ');

        return $nom !== '' ? $nom : 'piece-jointe';
    }

    private function nomFichierAscii(string $nom): string
    {
        $ascii = function_exists('iconv')
            ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nom) ?: $nom)
            : $nom;
        $ascii = preg_replace('/[^A-Za-z0-9._ ()-]+/', '_', $ascii) ?? '';
        $ascii = trim($ascii, '._ ');

        return $ascii !== '' ? $ascii : 'piece-jointe';
    }

    private function typeMime(?string $type): string
    {
        return match (strtolower((string) $type)) {
            'pdf'         => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            default       => 'application/octet-stream',
        };
    }

    private function typeMimeApercu(?string $type): ?string
    {
        return match (strtolower((string) $type)) {
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\Nature;
use App\Models\PieceJointe;
use App\Models\Service;
use App\Models\Statut;
use App\Services\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class DoleanceController extends Controller
{
    /**
     * GET /api/formulaire/jeton
     * Horodatage chiffré pour le délai anti-spam du dépôt public.
     */
    public function jeton(): JsonResponse
    {
        return response()->json([
            'jeton' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    /**
     * POST /api/doleances
     * Dépôt d'une doléance depuis le formulaire public (écran "Déposer une doléance").
     */
    public function store(Request $request)
    {
        $raisonSpam = $this->controlerAntiSpam($request);
        if ($raisonSpam !== null) {
            Log::warning('Dépôt public rejeté', [
                'ip'     => $request->ip(),
                'raison' => $raisonSpam,
            ]);

            return response()->json([
                'message' => "Votre demande n'a pas pu être envoyée. Rechargez la page et réessayez.",
            ], 422);
        }

        $telephone = $request->input('telephone');
        if (is_string($telephone)) {
            $request->merge(['telephone' => preg_replace('/\s+/', '', $telephone)]);
        }

        $nomPersonne = "/^[\\p{L} '\\-]+$/u";

        $data = $request->validate([
            'nom'          => ['required', 'string', 'max:60', 'regex:'.$nomPersonne],
            'prenom'       => ['required', 'string', 'max:60', 'regex:'.$nomPersonne],
            'email'        => ['required', 'email', 'max:120'],
            'telephone'    => ['required', 'string', 'max:20', 'regex:/^(\\+213|0)([5-7]\\d{8}|[2-4]\\d{7,8})$/'],
            'wilaya'       => ['required', 'string', 'max:40'],
            'objet'        => ['required', 'string', 'max:200'],
            'description'  => ['required', 'string', 'max:5000'],
            'id_service'   => [
                'required',
                Rule::exists('services', 'id_service')->where(
                    fn ($q) => $q->whereRaw('LOWER(TRIM(nom_service)) != ?', [mb_strtolower(Service::TOUS_LES_DOMAINES)])
                ),
            ],
            'id_nature'    => [
                'required',
                Rule::exists('natures', 'id_nature')->where(
                    fn ($q) => $q->whereRaw('LOWER(TRIM(libelle)) != ?', [mb_strtolower(Nature::TOUTES_NATURES)])
                ),
            ],
            'id_qualite'   => [
                'required',
                Rule::exists('qualites', 'id_qualite')->where('selectionnable', true),
            ],
            'piece_jointe' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5 Mo
        ]);

        $fichier = $request->file('piece_jointe');
        if ($fichier instanceof UploadedFile) {
            $extension = strtolower($fichier->getClientOriginalExtension());
            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)
                && ! $this->reencoderImageSansExif($fichier, $extension)) {
                return response()->json(['message' => 'Image invalide.'], 422);
            }
        }

        [$doleance, $evenement] = DB::transaction(function () use ($data, $fichier) {
            $statutNouvelle = Statut::parCode(Statut::NOUVELLE);

            $doleance = Doleance::create([
                'reference'   => $this->genererReference(),
                'nom'         => $data['nom'],
                'prenom'      => $data['prenom'],
                'email'       => $data['email'],
                'telephone'   => $data['telephone'],
                'wilaya'      => $data['wilaya'],
                'objet'       => $data['objet'],
                'description' => $data['description'],
                'date_depot'  => now(),
                'id_service'  => $data['id_service'],
                'id_statut'   => $statutNouvelle->id_statut,
                'id_nature'   => $data['id_nature'],
                'id_qualite'  => $data['id_qualite'],
            ]);

            if ($fichier instanceof UploadedFile) {
                $chemin = $fichier->store('pieces-jointes/'.$doleance->id_doleance, 'local');
                $extension = strtolower($fichier->getClientOriginalExtension());

                PieceJointe::create([
                    'nom_fichier' => $fichier->getClientOriginalName(),
                    'type'        => $extension === 'jpeg' ? 'jpg' : $extension,
                    'taille'      => filesize($fichier->getRealPath()) ?: $fichier->getSize(),
                    'chemin'      => $chemin,
                    'origine'     => 'DEPOT_INITIAL',
                    'id_doleance' => $doleance->id_doleance,
                ]);
            }

            $evenement = Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'depot',
                'visible_demandeur' => true,
                'id_doleance'       => $doleance->id_doleance,
                'id_statut_apres'   => $statutNouvelle->id_statut,
            ]);

            $nomService = Service::whereKey($doleance->id_service)->value('nom_service') ?? '—';
            Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'affectation',
                'detail'            => "Affectée au service {$nomService}",
                'visible_demandeur' => false,
                'id_doleance'       => $doleance->id_doleance,
                'id_utilisateur'    => null,
            ]);

            return [$doleance, $evenement];
        });

        NotificationDispatcher::emettre(
            'doleance_deposee',
            $doleance->fresh(['service.responsable', 'responsable', 'statut']),
            [
                'titre' => "Nouvelle doléance — {$doleance->reference}",
                'texte' => "Le dossier {$doleance->reference} a été déposé.",
            ],
            null,
            $evenement->id_evenement,
        );

        return response()->json([
            'reference' => $doleance->reference,
            'message'   => 'Votre doléance a bien été enregistrée.',
        ], 201);
    }

    /**
     * Champ piège + jeton horodaté (3 s à 2 h). Aucune donnée personnelle n'est journalisée.
     */
    private function controlerAntiSpam(Request $request): ?string
    {
        $piege = $request->input('site_web');
        if (is_string($piege) && trim($piege) !== '') {
            return 'honeypot';
        }

        $jeton = $request->input('jeton_formulaire');
        if (! is_string($jeton) || $jeton === '') {
            return 'jeton_absent';
        }

        try {
            $horodatage = (int) Crypt::decryptString($jeton);
        } catch (Throwable) {
            return 'jeton_invalide';
        }

        if ($horodatage <= 0) {
            return 'jeton_invalide';
        }

        $age = now()->timestamp - $horodatage;
        $min = (int) config('itassel.securite.formulaire_delai_min', 3);
        $max = (int) config('itassel.securite.formulaire_delai_max', 7200);

        if ($age < $min) {
            return 'jeton_trop_recent';
        }

        if ($age > $max) {
            return 'jeton_expire';
        }

        return null;
    }

    /**
     * Ré-encode JPEG/PNG via GD pour retirer EXIF (GPS). Refuse si GD manque ou si le décodage échoue.
     */
    private function reencoderImageSansExif(UploadedFile $fichier, string $extension): bool
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
     * Génère une référence unique au format ITS-AAAA-NNNNNN.
     */
    private function genererReference(): string
    {
        $annee = now()->year;

        do {
            $numero = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $reference = "ITS-{$annee}-{$numero}";
        } while (Doleance::where('reference', $reference)->exists());

        return $reference;
    }
}

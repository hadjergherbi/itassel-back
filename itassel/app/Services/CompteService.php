<?php

namespace App\Services;

use App\Exceptions\ConflitMetier;
use App\Mail\InvitationCompteMail;
use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\JetonMotDePasse;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CompteService
{
    /** @var array<string, true> */
    private static array $envoisAfterResponse = [];

    public static function creer(array $donnees, Utilisateur $acteur, Request $request): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->fill([
            'nom'        => $donnees['nom'],
            'prenom'     => $donnees['prenom'],
            'email'      => $donnees['email'],
            'actif'      => true,
            'id_service' => $donnees['role'] === 'super_admin' ? null : $donnees['id_service'],
        ]);
        $utilisateur->role = $donnees['role'];
        $utilisateur->mot_de_passe = Hash::make(Str::random(64));
        $utilisateur->mot_de_passe_defini_le = null;
        $utilisateur->save();

        JournalService::action($request, $acteur, 'creation_utilisateur', $utilisateur->nomComplet(), $utilisateur);

        static::envoyerInvitation($utilisateur, $acteur, $request);

        return $utilisateur->fresh('service');
    }

    public static function renvoyerInvitation(Utilisateur $cible, Utilisateur $acteur, Request $request): void
    {
        if ($cible->mot_de_passe_defini_le !== null || ! $cible->actif) {
            throw new ConflitMetier('invitation_inutile', 'Cette invitation ne peut pas être renvoyée.');
        }

        static::envoyerInvitation($cible, $acteur, $request);
    }

    public static function reinitialiserMotDePasse(Utilisateur $cible, ?Utilisateur $acteur, Request $request): void
    {
        $cible->mot_de_passe = Hash::make(Str::random(64));
        $cible->save();
        $cible->tokens()->delete();

        $clair = JetonService::emettre($cible, 'reinitialisation', $acteur);

        $ok = true;
        try {
            Mail::to($cible->email)->send(new ReinitialisationMotDePasseMail($cible, $clair));
        } catch (\Throwable) {
            $ok = false;
        }

        JournalService::action(
            $request,
            $acteur,
            'reinitialisation_mot_de_passe',
            $cible->nomComplet(),
            $cible,
            $ok ? 'succes' : 'echec',
        );
    }

    public static function envoyerLienReinitialisation(Utilisateur $utilisateur, Request $request): void
    {
        $jetonClair = JetonService::emettre($utilisateur, 'reinitialisation', null);

        JournalService::action(
            $request,
            null,
            'reinitialisation_mot_de_passe',
            'Demande en libre-service',
            $utilisateur,
            'succes',
        );

        $email = $utilisateur->email;
        $mail = new ReinitialisationMotDePasseMail($utilisateur, $jetonClair);
        $cleEnvoi = $utilisateur->id_utilisateur.':'.hash('sha256', $mail->lien);

        dispatch(function () use ($email, $mail, $utilisateur, $cleEnvoi) {
            if (isset(self::$envoisAfterResponse[$cleEnvoi])) {
                return;
            }
            self::$envoisAfterResponse[$cleEnvoi] = true;

            try {
                Mail::to($email)->send($mail);
            } catch (\Throwable $e) {
                Log::error('Échec envoi mail réinitialisation', [
                    'id_utilisateur' => $utilisateur->id_utilisateur,
                    'erreur'         => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    public static function motDePasseOublie(string $email, Request $request): void
    {
        $email = mb_strtolower(trim($email));
        $cle = 'mdp-oublie:'.sha1($email);

        if (RateLimiter::tooManyAttempts($cle, 3)) {
            return;
        }

        RateLimiter::hit($cle, 15 * 60);

        $utilisateur = static::trouverParEmail($email);

        if (! $utilisateur || ! $utilisateur->actif || $utilisateur->mot_de_passe_defini_le === null) {
            return;
        }

        $jetonRecent = JetonMotDePasse::query()
            ->where('id_utilisateur', $utilisateur->id_utilisateur)
            ->where('type', 'reinitialisation')
            ->whereNull('utilise_le')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->exists();

        if ($jetonRecent) {
            return;
        }

        static::envoyerLienReinitialisation($utilisateur, $request);
    }

    public static function definirMotDePasse(string $jeton, string $motDePasse, Request $request): Utilisateur
    {
        $enregistrement = JetonService::trouver($jeton);
        $utilisateur = $enregistrement?->utilisateur;

        if (! $enregistrement || ! $enregistrement->estUtilisable() || ! $utilisateur?->actif) {
            throw ValidationException::withMessages([
                'jeton' => ['Lien invalide ou expiré.'],
            ]);
        }

        $utilisateur->mot_de_passe = Hash::make($motDePasse);
        $utilisateur->mot_de_passe_defini_le = now();
        $utilisateur->save();

        $enregistrement->update(['utilise_le' => now()]);

        \App\Models\JetonMotDePasse::where('id_utilisateur', $utilisateur->id_utilisateur)
            ->whereNull('utilise_le')
            ->update(['utilise_le' => now()]);

        $utilisateur->tokens()->delete();

        JournalService::action($request, $utilisateur, 'mot_de_passe_defini', $utilisateur->nomComplet(), $utilisateur);

        return $utilisateur;
    }

    public static function changerMotDePasse(Utilisateur $utilisateur, string $actuel, string $nouveau, ?Request $request = null): void
    {
        if (! Hash::check($actuel, $utilisateur->mot_de_passe)) {
            throw ValidationException::withMessages([
                'mot_de_passe_actuel' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        if (Hash::check($nouveau, $utilisateur->mot_de_passe)) {
            throw ValidationException::withMessages([
                'mot_de_passe' => ['Le nouveau mot de passe doit être différent de l\'actuel.'],
            ]);
        }

        $utilisateur->mot_de_passe = Hash::make($nouveau);
        $utilisateur->mot_de_passe_defini_le = now();
        $utilisateur->save();

        $courant = $utilisateur->currentAccessToken();
        $idCourant = $courant && isset($courant->id) ? $courant->id : null;
        $utilisateur->tokens()
            ->when($idCourant, fn ($q) => $q->where('id', '!=', $idCourant))
            ->when(! $idCourant, fn ($q) => $q)
            ->delete();

        if ($request) {
            JournalService::action($request, $utilisateur, 'changement_mot_de_passe', $utilisateur->nomComplet(), $utilisateur);
        }
    }

    public static function verifierJeton(string $jeton): array
    {
        $enregistrement = JetonService::trouver($jeton);
        $utilisateur = $enregistrement?->utilisateur;
        $valide = $enregistrement && $enregistrement->estUtilisable() && $utilisateur?->actif;

        return [
            'valide' => (bool) $valide,
            'type'   => $valide ? $enregistrement->type : null,
            'email'  => $valide ? static::masquerEmail($utilisateur->email) : null,
            'prenom' => $valide ? $utilisateur->prenom : null,
        ];
    }

    public static function masquerEmail(string $email): string
    {
        [$local, $domaine] = array_pad(explode('@', $email, 2), 2, '');
        $debut = mb_substr($local, 0, 1);
        $fin = mb_strlen($local) > 1 ? mb_substr($local, -1) : '';

        return $debut.'***'.$fin.($domaine !== '' ? '@'.$domaine : '');
    }

    public static function trouverParEmail(string $email): ?Utilisateur
    {
        $email = mb_strtolower(trim($email));

        return Utilisateur::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private static function envoyerInvitation(Utilisateur $cible, Utilisateur $acteur, Request $request): void
    {
        $clair = JetonService::emettre($cible, 'invitation', $acteur);
        $ok = true;

        try {
            Mail::to($cible->email)->send(new InvitationCompteMail($cible, $clair));
            $cible->invitation_envoyee_le = now();
            $cible->save();
        } catch (\Throwable) {
            $ok = false;
        }

        JournalService::action(
            $request,
            $acteur,
            'invitation_envoyee',
            $cible->nomComplet(),
            $cible,
            $ok ? 'succes' : 'echec',
        );

        if (! $ok) {
            throw new RuntimeException("L'invitation n'a pas pu être envoyée.");
        }
    }
}

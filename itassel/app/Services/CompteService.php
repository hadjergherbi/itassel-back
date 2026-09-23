<?php

namespace App\Services;

use App\Exceptions\ConflitMetier;
use App\Mail\InvitationCompteMail;
use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CompteService
{
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

    public static function motDePasseOublie(string $email, Request $request): void
    {
        $utilisateur = Utilisateur::where('email', $email)->first();

        if ($utilisateur && $utilisateur->actif && $utilisateur->mot_de_passe_defini_le !== null) {
            static::reinitialiserMotDePasse($utilisateur, null, $request);
        }
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

    public static function changerMotDePasse(Utilisateur $utilisateur, string $actuel, string $nouveau): void
    {
        if (! Hash::check($actuel, $utilisateur->mot_de_passe)) {
            throw ValidationException::withMessages([
                'mot_de_passe_actuel' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $utilisateur->mot_de_passe = Hash::make($nouveau);
        $utilisateur->mot_de_passe_defini_le = $utilisateur->mot_de_passe_defini_le ?? now();
        $utilisateur->save();
        $utilisateur->tokens()->delete();
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

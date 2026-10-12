<?php

namespace App\Console\Commands;

use App\Models\Utilisateur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreerSuperAdmin extends Command
{
    protected $signature = 'itassel:creer-super-admin
        {email : Adresse du super administrateur}
        {--nom=Admin : Nom}
        {--prenom=Système : Prénom}';

    protected $description = 'Crée un super administrateur. Le mot de passe est demandé, ou généré et affiché une seule fois.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Adresse email invalide.');

            return self::FAILURE;
        }

        if (Utilisateur::withTrashed()->where('email', $email)->exists()) {
            $this->error('Un compte existe déjà avec cet email.');

            return self::FAILURE;
        }

        $genere = false;
        if (! $this->input->isInteractive()) {
            $motDePasse = Str::password(20);
            $genere = true;
        } else {
            $saisi = $this->secret('Mot de passe (laisser vide pour en générer un)');
            if (! is_string($saisi) || $saisi === '') {
                $motDePasse = Str::password(20);
                $genere = true;
            } elseif (mb_strlen($saisi) < 8) {
                $this->error('Le mot de passe doit contenir au moins 8 caractères.');

                return self::FAILURE;
            } else {
                $motDePasse = $saisi;
            }
        }

        $utilisateur = new Utilisateur;
        $utilisateur->forceFill([
            'nom' => (string) $this->option('nom'),
            'prenom' => (string) $this->option('prenom'),
            'email' => $email,
            'actif' => true,
            'id_service' => null,
            'role' => 'super_admin',
            'mot_de_passe' => Hash::make($motDePasse),
            'mot_de_passe_defini_le' => now(),
        ])->save();

        $this->info("Super administrateur créé : {$utilisateur->email}");

        if ($genere) {
            $this->line('Mot de passe (affiché une seule fois) : '.$motDePasse);
        }

        return self::SUCCESS;
    }
}

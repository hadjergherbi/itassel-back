<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Crée les deux comptes administrateurs de démonstration (super admin et admin du service Sport).
 * Uniquement en local/testing, ou si SEED_DEMO=true / db:seed --demo.
 * Un compte déjà présent n'est ni réactivé, ni restauré, ni réinitialisé.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->demoAutorise()) {
            $this->command?->warn('Comptes de démonstration ignorés : réservés à local/testing, ou à --demo / SEED_DEMO=true.');

            return;
        }

        $hash = Hash::make($this->motDePasseDemo());

        $this->enregistrer([
            'email' => 'demo.admin@itassel.dz',
            'nom' => 'Admin',
            'prenom' => 'Démo',
            'role' => 'super_admin',
            'id_service' => null,
            'mot_de_passe' => $hash,
        ]);

        $service = Service::where('nom_service', 'Sport')->first();
        if (! $service) {
            $this->command?->warn('Service « Sport » introuvable : lancez d\'abord les services du DatabaseSeeder.');

            return;
        }

        $admin = $this->enregistrer([
            'email' => 'demo.sport@itassel.dz',
            'nom' => 'Sport',
            'prenom' => 'Démo',
            'role' => 'admin_service',
            'id_service' => $service->id_service,
            'mot_de_passe' => $hash,
        ]);

        if ($admin && $service->id_responsable === null) {
            $service->update(['id_responsable' => $admin->id_utilisateur]);
        }

        $this->command?->info('Comptes de démonstration prêts.');
    }

    private function demoAutorise(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        if (config('itassel.seed_demo') === true) {
            return true;
        }

        return in_array('--demo', $_SERVER['argv'] ?? [], true);
    }

    private function motDePasseDemo(): string
    {
        $motDePasse = config('itassel.demo_password');
        if (is_string($motDePasse) && $motDePasse !== '') {
            return $motDePasse;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'DemoLocalOnly';
        }

        throw new RuntimeException(
            'ITASSEL_DEMO_PASSWORD est obligatoire pour créer les comptes de démonstration hors local et testing.'
        );
    }

    /**
     * @param  array{email: string, nom: string, prenom: string, role: string, id_service: ?int, mot_de_passe: string}  $donnees
     */
    private function enregistrer(array $donnees): ?Utilisateur
    {
        if (Utilisateur::withTrashed()->where('email', $donnees['email'])->exists()) {
            return null;
        }

        $utilisateur = new Utilisateur;
        $utilisateur->forceFill([
            'nom' => $donnees['nom'],
            'prenom' => $donnees['prenom'],
            'email' => $donnees['email'],
            'actif' => true,
            'id_service' => $donnees['id_service'],
            'role' => $donnees['role'],
            'mot_de_passe' => $donnees['mot_de_passe'],
            'mot_de_passe_defini_le' => now(),
        ])->save();

        return $utilisateur;
    }
}

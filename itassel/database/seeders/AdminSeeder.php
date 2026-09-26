<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Crée les deux comptes administrateurs de démonstration (super admin et admin du service Sport).
 * Nécessaire à l'installation pour pouvoir se connecter au back-office avant de créer les comptes métier.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $hash = Hash::make($this->motDePasseDemo());

        $this->enregistrer([
            'email'        => 'demo.admin@itassel.dz',
            'nom'          => 'Admin',
            'prenom'       => 'Démo',
            'role'         => 'super_admin',
            'id_service'   => null,
            'mot_de_passe' => $hash,
        ]);

        $service = Service::where('nom_service', 'Sport')->first();
        if (! $service) {
            $this->command?->warn('Service « Sport » introuvable : lancez d\'abord les services du DatabaseSeeder.');

            return;
        }

        $admin = $this->enregistrer([
            'email'        => 'demo.sport@itassel.dz',
            'nom'          => 'Sport',
            'prenom'       => 'Démo',
            'role'         => 'admin_service',
            'id_service'   => $service->id_service,
            'mot_de_passe' => $hash,
        ]);

        $service->update(['id_responsable' => $admin->id_utilisateur]);

        $this->command?->info('Comptes de démonstration prêts.');
    }

    private function motDePasseDemo(): string
    {
        $motDePasse = env('ADMIN_DEMO_PASSWORD');
        if (is_string($motDePasse) && $motDePasse !== '') {
            return $motDePasse;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'DemoLocalOnly';
        }

        throw new RuntimeException(
            'ADMIN_DEMO_PASSWORD est obligatoire hors des environnements local et testing.'
        );
    }

    private function enregistrer(array $donnees): Utilisateur
    {
        $utilisateur = Utilisateur::withTrashed()->firstOrNew(['email' => $donnees['email']]);

        if ($utilisateur->trashed()) {
            $utilisateur->restore();
        }

        $utilisateur->forceFill([
            'nom'                    => $donnees['nom'],
            'prenom'                 => $donnees['prenom'],
            'actif'                  => true,
            'id_service'             => $donnees['id_service'],
            'role'                   => $donnees['role'],
            'mot_de_passe'           => $donnees['mot_de_passe'],
            'mot_de_passe_defini_le' => $utilisateur->mot_de_passe_defini_le ?? now(),
        ])->save();

        return $utilisateur;
    }
}

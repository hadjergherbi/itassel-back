<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    // Mot de passe de DÉMONSTRATION : à changer avant toute mise en ligne.
    private const MOT_DE_PASSE_DEMO = 'Itassel2026!';

    public function run(): void
    {
        $hash = Hash::make(self::MOT_DE_PASSE_DEMO);

        // Super administrateur (sans service)
        Utilisateur::updateOrCreate(
            ['email' => 'nour.belkacem@itassel.dz'],
            ['nom' => 'Belkacem', 'prenom' => 'Nour', 'mot_de_passe' => $hash,
             'role' => 'super_admin', 'actif' => true, 'id_service' => null],
        );

        // Un administrateur par service, désigné responsable du service
        $comptes = [
            'Sport'               => ['amine.kaddour@itassel.dz', 'Kaddour', 'Amine'],
            'Jeunesse'            => ['farid.merabet@itassel.dz', 'Merabet', 'Farid'],
            'Ressources humaines' => ['samira.bensalem@itassel.dz', 'Bensalem', 'Samira'],
        ];

        foreach ($comptes as $nomService => [$email, $nom, $prenom]) {
            $service = Service::where('nom_service', $nomService)->first();
            if (! $service) {
                $this->command?->warn("Service « {$nomService} » introuvable : lancez d'abord php artisan db:seed");
                continue;
            }

            $admin = Utilisateur::updateOrCreate(
                ['email' => $email],
                ['nom' => $nom, 'prenom' => $prenom, 'mot_de_passe' => $hash,
                 'role' => 'admin_service', 'actif' => true, 'id_service' => $service->id_service],
            );

            $service->update(['id_responsable' => $admin->id_utilisateur]);
        }

        // Anciens comptes de test dont le mot de passe est en clair : on le remplace
        // par le mot de passe de démonstration chiffré, pour qu'ils puissent se connecter.
        Utilisateur::where('mot_de_passe', 'not like', '$2y$%')
            ->get()
            ->each(fn ($u) => $u->update(['mot_de_passe' => $hash]));

        $this->command?->info('Comptes prêts. Mot de passe de démonstration : '.self::MOT_DE_PASSE_DEMO);
    }
}
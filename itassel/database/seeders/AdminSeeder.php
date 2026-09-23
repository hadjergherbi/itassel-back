<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    private const MOT_DE_PASSE_DEMO = 'Itassel2026!';

    public function run(): void
    {
        $hash = Hash::make(self::MOT_DE_PASSE_DEMO);

        $this->enregistrer([
            'email'        => 'nour.belkacem@itassel.dz',
            'nom'          => 'Belkacem',
            'prenom'       => 'Nour',
            'role'         => 'super_admin',
            'id_service'   => null,
            'mot_de_passe' => $hash,
        ]);

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

            $admin = $this->enregistrer([
                'email'        => $email,
                'nom'          => $nom,
                'prenom'       => $prenom,
                'role'         => 'admin_service',
                'id_service'   => $service->id_service,
                'mot_de_passe' => $hash,
            ]);

            $service->update(['id_responsable' => $admin->id_utilisateur]);
        }

        Utilisateur::where('mot_de_passe', 'not like', '$2y$%')
            ->get()
            ->each(function (Utilisateur $utilisateur) use ($hash) {
                $utilisateur->mot_de_passe = $hash;
                $utilisateur->mot_de_passe_defini_le = $utilisateur->mot_de_passe_defini_le ?? now();
                $utilisateur->save();
            });

        $this->command?->info('Comptes prêts. Mot de passe de démonstration : '.self::MOT_DE_PASSE_DEMO);
    }

    private function enregistrer(array $donnees): Utilisateur
    {
        $utilisateur = Utilisateur::firstOrNew(['email' => $donnees['email']]);
        $utilisateur->fill([
            'nom'        => $donnees['nom'],
            'prenom'     => $donnees['prenom'],
            'actif'      => true,
            'id_service' => $donnees['id_service'],
        ]);
        $utilisateur->role = $donnees['role'];
        $utilisateur->mot_de_passe = $donnees['mot_de_passe'];
        $utilisateur->mot_de_passe_defini_le = $utilisateur->mot_de_passe_defini_le ?? now();
        $utilisateur->save();

        return $utilisateur;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Nature;
use App\Models\Qualite;
use App\Models\Service;
use App\Models\Statut;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $messagesCitoyen = [
            'resolue'          => 'Une réponse est disponible.',
            'reponse_apportee' => 'Une réponse à votre demande est disponible.',
            'hors_competence'  => 'Votre demande relève d\'un autre organisme.',
            'non_retenue'      => 'Votre réclamation n\'a pas été retenue après examen.',
            'double'           => 'Votre demande a déjà été enregistrée.',
        ];

        $statuts = [
            ['nouvelle', 'Nouvelle doléance', 'bleu', true, 10],
            ['en_cours', 'En cours', 'orange', true, 20],
            ['information_demandee', 'Information demandée', 'violet', true, 25],
            ['resolue', 'Résolu', 'vert', true, 30],
            ['reponse_apportee', 'Réponse apportée', 'vert', true, 40],
            ['hors_competence', 'Hors compétence', 'gris', true, 50],
            ['non_retenue', 'Non fondée', 'rouge', true, 60],
            ['double', 'Double doléance', 'turquoise', true, 70],
            ['non_fondee', 'Ancien classement — non fondée', 'rouge', false, 80],
            ['cloturee', 'Clôturée', 'gris', false, 90],
        ];
        foreach ($statuts as [$code, $libelle, $couleur, $selectionnable, $ordre]) {
            Statut::updateOrCreate(
                ['code' => $code],
                [
                    'libelle'          => $libelle,
                    'couleur'          => $couleur,
                    'selectionnable'   => $selectionnable,
                    'ordre'            => $ordre,
                    'message_citoyen'  => $messagesCitoyen[$code] ?? null,
                ]
            );
        }

        foreach (['Sport', 'Jeunesse', 'Ressources humaines'] as $nom) {
            Service::firstOrCreate(['nom_service' => $nom]);
        }

        $natures = [
            ['Réclamation', 'reclamation'],
            ['Signalement', 'reclamation'],
            ['Suggestion', 'demande'],
            ["Demande d'information", 'demande'],
        ];
        foreach ($natures as [$libelle, $famille]) {
            Nature::updateOrCreate(['libelle' => $libelle], ['famille' => $famille]);
        }

        foreach (['Citoyen', 'Association', 'Sportif', 'Parent'] as $libelle) {
            Qualite::firstOrCreate(['libelle' => $libelle]);
        }

        $this->call(AdminSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(ParametreNotificationSeeder::class);
    }
}
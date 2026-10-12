<?php

namespace Database\Seeders;

use App\Models\Nature;
use App\Models\Qualite;
use App\Models\Service;
use App\Models\Statut;
use Illuminate\Database\Seeder;

/**
 * Seeder principal : référentiels (statuts, services, natures, qualités) puis comptes démo, rôles et notifications.
 * À lancer après les migrations pour qu'une installation neuve ait les données indispensables à l'application.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $messagesCitoyen = [
            'resolue' => 'Une réponse est disponible.',
            'reponse_apportee' => 'Une réponse à votre demande est disponible.',
            'hors_competence' => 'Votre demande relève d\'un autre organisme.',
            'non_retenue' => 'Votre réclamation n\'a pas été retenue après examen.',
            'double' => 'Votre demande a déjà été enregistrée.',
        ];

        $statuts = [
            ['nouvelle', 'Nouvelle doléance', 'bleu', true, 10],
            ['en_cours', 'En cours', 'orange', true, 20],
            ['information_demandee', 'Information demandée', 'violet', true, 25],
            ['resolue', 'Résolue', 'vert', true, 30],
            ['reponse_apportee', 'Réponse apportée', 'vert', true, 40],
            ['hors_competence', 'Hors compétence', 'gris', true, 50],
            ['non_retenue', 'Non retenue', 'rouge', true, 60],
            ['double', 'Double doléance', 'turquoise', true, 70],
            ['non_fondee', 'Ancien classement — non fondée', 'rouge', false, 80],
            ['cloturee', 'Clôturée', 'gris', false, 90],
        ];
        foreach ($statuts as [$code, $libelle, $couleur, $selectionnable, $ordre]) {
            Statut::firstOrCreate(
                ['code' => $code],
                [
                    'libelle' => $libelle,
                    'couleur' => $couleur,
                    'selectionnable' => $selectionnable,
                    'ordre' => $ordre,
                    'message_citoyen' => $messagesCitoyen[$code] ?? null,
                ]
            );
        }

        // Référentiels identifiés par le libellé : on ne les recrée que sur une base vide,
        // sinon un libellé renommé ou supprimé reviendrait au déploiement suivant.
        if (! Service::query()->exists()) {
            foreach (['Sport', 'Jeunesse', 'Ressources humaines', Service::TOUS_LES_DOMAINES] as $nom) {
                Service::create(['nom_service' => $nom]);
            }
        }

        // La migration crée déjà « Toutes natures ». Le reste du catalogue n'est inséré
        // que si aucune autre nature n'existe : un libellé renommé ou supprimé n'est pas recréé.
        $naturesMetier = Nature::query()
            ->whereRaw('LOWER(TRIM(libelle)) != ?', [mb_strtolower(Nature::TOUTES_NATURES)])
            ->exists();
        if (! $naturesMetier) {
            $natures = [
                ['Réclamation', 'reclamation'],
                ['Signalement', 'reclamation'],
                ['Suggestion', 'demande'],
                ["Demande d'information", 'demande'],
                [Nature::TOUTES_NATURES, 'reclamation'],
            ];
            foreach ($natures as [$libelle, $famille]) {
                Nature::firstOrCreate(['libelle' => $libelle], ['famille' => $famille]);
            }
        }

        if (! Qualite::query()->exists()) {
            foreach (Qualite::LIBELLES as $index => $libelle) {
                Qualite::create([
                    'libelle' => $libelle,
                    'selectionnable' => true,
                    'ordre' => ($index + 1) * 10,
                ]);
            }
        }

        $this->call(AdminSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(ParametreNotificationSeeder::class);
        $this->call(ModeleMessageSeeder::class);
    }
}

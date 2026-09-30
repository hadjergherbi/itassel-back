<?php

namespace Database\Seeders;

use App\Models\ModeleMessage;
use Illuminate\Database\Seeder;

/**
 * Un modèle d'exemple par type d'usage (config itassel.messages_usages).
 * Textes sans variables, prêts à être adaptés dans Paramètres.
 */
class ModeleMessageSeeder extends Seeder
{
    public function run(): void
    {
        $modeles = [
            'accuse_reception' => [
                'Accusé de réception',
                'Nous avons bien reçu votre doléance. Elle est transmise au service concerné pour étude.',
            ],
            'prise_en_charge' => [
                'Prise en charge',
                'Votre dossier est désormais pris en charge. Nos services poursuivent son examen.',
            ],
            'complement' => [
                'Demande de précision',
                'Pour poursuivre l\'étude de votre dossier, merci de nous apporter les précisions demandées.',
            ],
            'reponse' => [
                'Réponse favorable',
                'Après examen, votre demande a été traitée. Nous vous remercions pour votre signalement.',
            ],
            'non_retenue' => [
                'Réclamation non retenue',
                'Après examen, votre réclamation n\'a pas été retenue. Le dossier est classé en conséquence.',
            ],
            'hors_competence' => [
                'Hors compétence',
                'Votre demande ne relève pas de la compétence de nos services. Nous vous invitons à vous adresser à l\'organisme compétent.',
            ],
            'double' => [
                'Dossier en double',
                'Votre demande correspond à un dossier déjà enregistré. Le présent dossier est classé comme doublon.',
            ],
            'relance' => [
                'Information sur le délai',
                'Votre dossier est toujours en cours de traitement. Nous vous remercions de votre patience.',
            ],
            'cloture' => [
                'Clôture du dossier',
                'Votre dossier est clôturé. Aucune suite n\'est prévue de notre part à ce stade.',
            ],
            'autre' => [
                'Message libre',
                'Nous vous informons de la suite donnée à votre dossier.',
            ],
        ];

        foreach ($modeles as $usage => [$titre, $contenu]) {
            ModeleMessage::updateOrCreate(
                ['titre' => $titre],
                ['type_usage' => $usage, 'contenu' => $contenu],
            );
        }

        $this->command?->info(count($modeles).' modèles de message prêts.');
    }
}

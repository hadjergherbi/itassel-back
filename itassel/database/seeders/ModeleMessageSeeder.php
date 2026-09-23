<?php

namespace Database\Seeders;

use App\Models\ModeleMessage;
use Illuminate\Database\Seeder;

class ModeleMessageSeeder extends Seeder
{
    public function run(): void
    {
        $modeles = [
            ['Accusé de réception', 'reponse',
             "Nous avons bien reçu votre doléance. Elle est en cours d'étude par nos services, et vous serez informé(e) de la suite qui lui sera donnée."],
            ['Demande traitée', 'reponse',
             "Votre demande a été traitée. Nous vous remercions pour votre signalement, qui contribue à l'amélioration de nos services."],
            ['Hors compétence', 'reponse',
             "Après étude, votre demande ne relève pas de la compétence du Ministère. Nous vous invitons à vous rapprocher de l'organisme concerné."],
            ['Précision sur le lieu', 'complement',
             "Pouvez-vous préciser l'adresse exacte du lieu concerné ?"],
            ['Justificatif', 'complement',
             "Merci de nous transmettre un justificatif permettant d'étudier votre demande."],
        ];

        foreach ($modeles as [$titre, $usage, $contenu]) {
            ModeleMessage::updateOrCreate(
                ['titre' => $titre],
                ['type_usage' => $usage, 'contenu' => $contenu],
            );
        }

        $this->command?->info(count($modeles).' modèles de message prêts.');
    }
}

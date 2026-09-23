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
        // "Nouvelle" doit rester écrit exactement ainsi : DoleanceController le cherche par ce libellé.
        $statuts = [
            ['Nouvelle', 'bleu'],
            ['En cours de traitement', 'orange'],
            ['Information demandée', 'violet'],
            ['Traitée', 'vert'],
            ['Clôturée', 'gris'],
            ['Non fondée', 'rouge'],
            ['Double doléance', 'turquoise'],
        ];
        foreach ($statuts as [$libelle, $couleur]) {
            Statut::updateOrCreate(['libelle' => $libelle], ['couleur' => $couleur]);
        }

        foreach (['Sport', 'Jeunesse', 'Ressources humaines'] as $nom) {
            Service::firstOrCreate(['nom_service' => $nom]);
        }

        foreach (['Réclamation', 'Suggestion', "Demande d'information"] as $libelle) {
            Nature::firstOrCreate(['libelle' => $libelle]);
        }

        foreach (['Citoyen', 'Association', 'Sportif', 'Parent'] as $libelle) {
            Qualite::firstOrCreate(['libelle' => $libelle]);
        }

        $this->call(AdminSeeder::class);
    }
}
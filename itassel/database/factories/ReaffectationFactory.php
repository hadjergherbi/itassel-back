<?php

namespace Database\Factories;

use App\Models\Doleance;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reaffectation>
 */
class ReaffectationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'etat'               => 'en_attente',
            'motif'              => fake()->sentence(),
            'date_demande'       => now(),
            'id_doleance'        => Doleance::factory(),
            'id_demandeur'       => Utilisateur::factory(),
            'id_service_propose' => Service::factory(),
        ];
    }
}

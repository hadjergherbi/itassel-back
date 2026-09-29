<?php

namespace Database\Factories;

use App\Models\Doleance;
use App\Models\Nature;
use App\Models\Qualite;
use App\Models\Service;
use App\Models\Statut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doleance>
 */
class DoleanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'ITS-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT),
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->safeEmail(),
            'telephone' => '055'.fake()->numerify('#######'),
            'wilaya' => fake()->city(),
            'objet' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'date_depot' => now(),
            'id_service' => Service::factory(),
            'id_statut' => Statut::factory(),
            'id_nature' => Nature::factory(),
            'id_qualite' => Qualite::factory(),
        ];
    }
}

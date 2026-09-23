<?php

namespace Database\Factories;

use App\Models\Nature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nature>
 */
class NatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'libelle' => fake()->unique()->words(2, true),
            'famille' => 'reclamation',
        ];
    }
}

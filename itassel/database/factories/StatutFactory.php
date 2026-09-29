<?php

namespace Database\Factories;

use App\Models\Statut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Statut>
 */
class StatutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'libelle' => fake()->unique()->words(2, true),
            'couleur' => fake()->randomElement(['bleu', 'orange', 'vert', 'rouge', 'gris']),
            'selectionnable' => true,
            'ordre' => 0,
        ];
    }
}

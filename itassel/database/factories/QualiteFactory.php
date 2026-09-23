<?php

namespace Database\Factories;

use App\Models\Qualite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Qualite>
 */
class QualiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'libelle' => fake()->unique()->words(2, true),
        ];
    }
}

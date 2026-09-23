<?php

namespace Database\Factories;

use App\Models\Complement;
use App\Models\Doleance;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complement>
 */
class ComplementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question'      => fake()->sentence(),
            'piece_exigee'  => false,
            'etat'          => 'en_attente',
            'date_demande'  => now(),
            'id_doleance'   => Doleance::factory(),
            'id_auteur'     => Utilisateur::factory(),
        ];
    }

    public function recu(): static
    {
        return $this->state(fn () => [
            'etat'         => 'recu',
            'reponse'      => fake()->sentence(),
            'date_reponse' => now(),
        ]);
    }
}

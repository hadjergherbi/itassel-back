<?php

namespace Database\Factories;

use App\Models\Journal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date_action' => now(),
            'compte'      => fake()->safeEmail(),
            'action'      => 'connexion',
            'adresse_ip'  => '127.0.0.1',
            'resultat'    => 'succes',
        ];
    }
}

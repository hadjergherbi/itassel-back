<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Utilisateur>
 */
class UtilisateurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom'          => fake()->lastName(),
            'prenom'       => fake()->firstName(),
            'email'                   => fake()->unique()->safeEmail(),
            'actif'                   => true,
            'id_service'              => Service::factory(),
            'mot_de_passe_defini_le'  => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Utilisateur $utilisateur) {
            if (! $utilisateur->mot_de_passe) {
                $utilisateur->mot_de_passe = Hash::make('password');
            }
            if (! $utilisateur->role) {
                $utilisateur->role = 'admin_service';
            }
        });
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role'       => 'super_admin',
            'id_service' => null,
        ]);
    }

    public function adminService(?Service $service = null): static
    {
        return $this->state(fn () => [
            'role'       => 'admin_service',
            'id_service' => $service?->id_service ?? Service::factory(),
        ]);
    }
}

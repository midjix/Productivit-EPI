<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.mb_strtoupper(fake()->lastName()),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'matricule' => fake()->unique()->numerify('EPI####'),
            'dsk_no_pers' => fake()->unique()->numberBetween(1000, 99999),
            'epilogg_user_id' => fake()->unique()->numberBetween(1, 9999),
            'service_id' => Service::factory(),
            'role' => Role::Salarie,
            'actif' => true,
            'date_entree' => fake()->dateTimeBetween('-8 years', '-3 months'),
            'date_sortie' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function coordinateur(): static
    {
        return $this->state(fn () => ['role' => Role::Coordinateur]);
    }

    public function administrateur(): static
    {
        return $this->state(fn () => ['role' => Role::Administrateur]);
    }

    /** Salarié parti : inactif, avec date de sortie. */
    public function sorti(): static
    {
        return $this->state(fn () => [
            'actif' => false,
            'date_sortie' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    /** Salarié présent dans DSK mais sans correspondance EpiLogg (cas d'alerte de l'import). */
    public function sansEpilogg(): static
    {
        return $this->state(fn () => ['epilogg_user_id' => null]);
    }
}

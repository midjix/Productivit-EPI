<?php

namespace Database\Factories;

use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DonneeDsk> */
class DonneeDskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'compte_dsk_id' => CompteDsk::factory(),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'minutes' => fake()->randomElement([480, 456, 420, 240]),
            'import_id' => null,
        ];
    }
}

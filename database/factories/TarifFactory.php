<?php

namespace Database\Factories;

use App\Models\Tarif;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tarif> */
class TarifFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('#.#'),
            'libelle' => 'Tarif '.fake()->unique()->word(),
            'facturable' => true,
            'actif' => true,
        ];
    }

    /** Activité interne : forfaits non facturables, déduits des heures dispo client. */
    public function interne(): static
    {
        return $this->state(fn () => [
            'code' => Tarif::CODE_AUTRE,
            'libelle' => 'Activité interne',
            'facturable' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\CategorieSuivi;
use App\Models\Service;
use App\Models\Tarif;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CategorieSuivi> */
class CategorieSuiviFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tarif_id' => Tarif::factory(),
            'epilogg_attend_type_id' => fake()->unique()->numberBetween(1, 9999),
            'libelle' => fake()->unique()->words(2, true),
            'service_id' => Service::factory(),
            'actif' => true,
        ];
    }

    /** Type importé d'EpiLogg mais pas encore rattaché à un tarif (cas d'alerte). */
    public function nonQualifie(): static
    {
        return $this->state(fn () => ['tarif_id' => null]);
    }
}

<?php

namespace Database\Factories;

use App\Models\RegleConversion;
use App\Models\Tarif;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegleConversion> */
class RegleConversionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tarif_id' => Tarif::factory(),
            'minutes' => fake()->randomElement([30, 45, 60, 90]),
            'date_debut_validite' => '2020-01-01',
            'date_fin_validite' => null,
            'commentaire' => null,
            'created_by' => null,
        ];
    }

    /** Règle close : barème d'une période passée. */
    public function close(string $debut, string $fin): static
    {
        return $this->state(fn () => [
            'date_debut_validite' => $debut,
            'date_fin_validite' => $fin,
        ]);
    }
}

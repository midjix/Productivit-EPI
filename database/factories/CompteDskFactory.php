<?php

namespace Database\Factories;

use App\Enums\CategorieHeure;
use App\Models\CompteDsk;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompteDsk> */
class CompteDskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'no_account' => fake()->unique()->numberBetween(1, 999),
            'libelle' => fake()->unique()->words(2, true),
            'type_dsk' => null,
            'categorie' => CategorieHeure::Autre,
            'compte_effectif' => false,
            'actif' => true,
        ];
    }

    public function categorie(CategorieHeure $categorie, bool $effectif = true): static
    {
        return $this->state(fn () => [
            'categorie' => $categorie,
            'compte_effectif' => $effectif,
            'libelle' => $categorie->label(),
        ]);
    }
}

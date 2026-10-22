<?php

namespace Database\Factories;

use App\Enums\SourceImport;
use App\Enums\StatutImport;
use App\Models\Import;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Import> */
class ImportFactory extends Factory
{
    public function definition(): array
    {
        $debut = fake()->dateTimeBetween('-2 months', '-1 day');

        return [
            'source' => fake()->randomElement(SourceImport::cases()),
            'statut' => StatutImport::Succes,
            'periode_debut' => (clone $debut)->modify('-60 days')->format('Y-m-d'),
            'periode_fin' => $debut->format('Y-m-d'),
            'nb_lignes' => fake()->numberBetween(50, 2000),
            'message' => null,
            'user_id' => null,
            'demarre_le' => $debut,
            'termine_le' => (clone $debut)->modify('+2 minutes'),
        ];
    }

    public function echec(string $message = 'Connexion à la source impossible'): static
    {
        return $this->state(fn () => ['statut' => StatutImport::Echec, 'message' => $message, 'nb_lignes' => 0]);
    }
}

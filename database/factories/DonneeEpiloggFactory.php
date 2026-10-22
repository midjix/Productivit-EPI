<?php

namespace Database\Factories;

use App\Models\CategorieSuivi;
use App\Models\DonneeEpilogg;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DonneeEpilogg> */
class DonneeEpiloggFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'categorie_suivi_id' => CategorieSuivi::factory(),
            'type_prestation' => fake()->randomElement([
                'Entretien / réunion avec bénéficiaire ou entourage immédiat',
                'Contact téléphonique avec bénéficiaire ou entourage immédiat',
                'Concertation professionnelle',
                'Préparation ou documentation des interventions',
                'Echange de courrier ou de courriel',
                'Déplacement',
            ]),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'quantite' => fake()->randomElement([0.5, 1, 1, 1, 1.5, 2, 3]),
            'reference_externe' => (string) fake()->unique()->numberBetween(100000, 9999999),
            'import_id' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Etl\CalculEfficacite;
use App\Models\StatistiqueMensuelle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Lignes de cache COHÉRENTES : les colonnes dérivées (total, dispo EPI, dispo client,
 * ratios) sont produites par le moteur `CalculEfficacite`, jamais tirées au hasard.
 * Réservée aux tests de l'API de consultation ; en fonctionnement réel, seul
 * le ConsolidationService écrit dans cette table.
 *
 * @extends Factory<StatistiqueMensuelle>
 */
class StatistiqueMensuelleFactory extends Factory
{
    public function definition(): array
    {
        $calcul = new CalculEfficacite(
            minutesNormales: fake()->numberBetween(80, 140) * 60,
            minutesConge: fake()->randomElement([0, 0, 480, 960, 1920]),
            minutesMaladie: fake()->randomElement([0, 0, 0, 480]),
            minutesFormation: fake()->randomElement([0, 0, 240, 480]),
            minutesSupervision: fake()->randomElement([0, 0, 120]),
            nbPrestations: fake()->numberBetween(40, 120).'.50',
            minutesFacturables: (fake()->numberBetween(50, 110) * 60).'.00',
            minutesActiviteInterne: fake()->randomElement(['0.00', '60.00', '120.00']),
        );

        return [
            'user_id' => User::factory(),
            'annee' => 2026,
            'mois' => fake()->numberBetween(1, 12),
            ...$calcul->attributs(),
            'calcule_le' => now(),
        ];
    }

    public function periode(int $annee, int $mois): static
    {
        return $this->state(fn () => ['annee' => $annee, 'mois' => $mois]);
    }

    /** Exemple 1 du fichier de la Fondation (avril 2026) : 128 h → 106 h → 98 h, 82 h facturables, efficacité 0,8367. */
    public function exempleFondation(): static
    {
        $calcul = new CalculEfficacite(
            minutesNormales: 100 * 60,
            minutesConge: (12 + 4) * 60,
            minutesMaladie: 6 * 60,
            minutesFormation: 4 * 60,
            minutesSupervision: 2 * 60,
            nbPrestations: '84.00',
            minutesFacturables: '4920.00',
            minutesActiviteInterne: '120.00',
        );

        return $this->state(fn () => $calcul->attributs());
    }

    /** Mois sans aucune donnée (salarié absent tout le mois) : ratios NULL. */
    public function vide(): static
    {
        return $this->state(fn () => (new CalculEfficacite)->attributs());
    }
}

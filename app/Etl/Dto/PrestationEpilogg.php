<?php

namespace App\Etl\Dto;

use Carbon\CarbonImmutable;

/**
 * Prestation EpiLogg (`benefits` joint à `attends` et `interventions`).
 * Aucune donnée sur le bénéficiaire : `customer_id` n'est jamais lu.
 */
final readonly class PrestationEpilogg
{
    public function __construct(
        public int $id,
        public int $utilisateurId,
        public ?string $email,
        public CarbonImmutable $date,
        public int $typeSuiviId,
        public ?string $typePrestation,
        public string $quantite,
        public bool $forfaitDeplacement,
        public ?int $minutesService,
        public ?int $minutesDeplacement,
        public ?CarbonImmutable $modifieLe,
    ) {}
}

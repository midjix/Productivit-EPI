<?php

namespace App\Etl\Dto;

use Carbon\CarbonImmutable;

/** Une valeur calculée DSK : (salarié, jour, compteur) → minutes (GET /accounts/calc/all). */
final readonly class MinutesCompteDsk
{
    public function __construct(
        public int $noPers,
        public CarbonImmutable $date,
        public int $noAccount,
        public int $minutes,
    ) {}
}

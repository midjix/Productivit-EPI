<?php

namespace App\Etl\Dto;

use Carbon\CarbonImmutable;

/**
 * Salarié DSK (GET /listAll/details → `pers`). Seuls les champs utiles au calcul et à
 * l'appariement sont conservés ; tout le reste de la réponse DSK (adresse, IBAN, date de
 * naissance…) est ignoré dès la lecture (minimisation RGPD).
 */
final readonly class SalarieDsk
{
    public function __construct(
        public int $noPers,
        public string $nomComplet,
        public ?string $codePers,
        public ?string $codeDept,
        public ?string $wageNum,
        public ?string $email,
        public ?CarbonImmutable $dateEntree,
        public ?CarbonImmutable $dateSortie,
    ) {}

    public function emailNormalise(): ?string
    {
        $e = $this->email === null ? '' : mb_strtolower(trim($this->email));

        return $e === '' ? null : $e;
    }
}

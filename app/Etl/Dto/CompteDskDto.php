<?php

namespace App\Etl\Dto;

/** Compteur DSK (GET /accounts) : identifiant, libellé, type brut (ABSENCE / SYSTEM). */
final readonly class CompteDskDto
{
    public function __construct(
        public int $noAccount,
        public string $libelle,
        public ?int $type,
    ) {}
}

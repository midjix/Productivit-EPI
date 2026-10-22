<?php

namespace App\Etl\Dto;

/** Département DSK (GET /departments) — correspond à un `Service` de l'organigramme. */
final readonly class DepartementDsk
{
    public function __construct(
        public string $codeDept,
        public string $libelle,
        public bool $actif,
    ) {}
}

<?php

namespace App\Etl\Dto;

/** Ligne de `attend_types` EpiLogg, telle que lue (sans interprétation). */
final readonly class TypeSuiviEpilogg
{
    public function __construct(
        public int $id,
        public string $libelle,
        public ?string $rateName,
        public ?int $minutesParForfait,
        public bool $archive,
    ) {}
}

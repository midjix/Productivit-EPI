<?php

namespace App\Etl\Dto;

/** Compte utilisateur EpiLogg : seule l'adresse email sert à l'appariement avec DSK. */
final readonly class SalarieEpilogg
{
    public function __construct(
        public int $id,
        public ?string $email,
        public ?string $nom,
        public bool $actif,
    ) {}

    public function emailNormalise(): ?string
    {
        return $this->email === null ? null : mb_strtolower(trim($this->email));
    }
}

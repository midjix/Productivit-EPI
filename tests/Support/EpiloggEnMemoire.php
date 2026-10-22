<?php

namespace Tests\Support;

use App\Enums\SourceImport;
use App\Etl\Contracts\ConnecteurEpilogg;
use Carbon\CarbonInterface;
use RuntimeException;

/** Doublure du connecteur EpiLogg (voir DskEnMemoire). */
final class EpiloggEnMemoire implements ConnecteurEpilogg
{
    public array $types = [];

    public array $salaries = [];

    public array $prestations = [];

    public bool $enPanne = false;

    public function source(): SourceImport
    {
        return SourceImport::Epilogg;
    }

    public function verifierConnexion(): void
    {
        if ($this->enPanne) {
            throw new RuntimeException('EpiLogg injoignable (simulation)');
        }
    }

    public function typesDeSuivi(): iterable
    {
        return $this->types;
    }

    public function salaries(): iterable
    {
        return $this->salaries;
    }

    public function prestations(CarbonInterface $du, CarbonInterface $au): iterable
    {
        return array_filter($this->prestations, fn ($p) => $p->date->betweenIncluded($du, $au));
    }
}

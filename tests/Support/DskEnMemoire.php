<?php

namespace Tests\Support;

use App\Enums\SourceImport;
use App\Etl\Contracts\ConnecteurDsk;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Doublure du connecteur DSK : des tableaux de DTO en mémoire, modifiables entre deux imports
 * (pour simuler une correction ou une suppression côté source). Le pattern Adaptateur rend
 * ceci trivial : l'EtlService ne voit aucune différence avec l'API HTTP réelle.
 */
final class DskEnMemoire implements ConnecteurDsk
{
    public array $departements = [];

    public array $comptes = [];

    public array $salaries = [];

    public array $minutes = [];

    public bool $enPanne = false;

    public function source(): SourceImport
    {
        return SourceImport::Dsk;
    }

    public function verifierConnexion(): void
    {
        if ($this->enPanne) {
            throw new RuntimeException('DSK injoignable (simulation)');
        }
    }

    public function departements(): iterable
    {
        return $this->departements;
    }

    public function comptes(): iterable
    {
        return $this->comptes;
    }

    public function salaries(CarbonInterface $du, CarbonInterface $au): iterable
    {
        return $this->salaries;
    }

    public function minutesParJour(CarbonInterface $du, CarbonInterface $au): iterable
    {
        return array_filter($this->minutes, fn ($m) => $m->date->betweenIncluded($du, $au));
    }
}

<?php

namespace App\Etl\Contracts;

use App\Etl\Dto\PrestationEpilogg;
use App\Etl\Dto\SalarieEpilogg;
use App\Etl\Dto\TypeSuiviEpilogg;
use Carbon\CarbonInterface;

/** Lecture des données EpiLogg nécessaires au calcul — et rien d'autre (minimisation RGPD). */
interface ConnecteurEpilogg extends ConnecteurSource
{
    /** @return iterable<TypeSuiviEpilogg> tous les types de suivi, archivés compris */
    public function typesDeSuivi(): iterable;

    /** @return iterable<SalarieEpilogg> comptes utilisateurs EpiLogg (id, email, nom, actif) */
    public function salaries(): iterable;

    /**
     * Prestations dont la date de réalisation est dans [du, au] inclus.
     *
     * @return iterable<PrestationEpilogg>
     */
    public function prestations(CarbonInterface $du, CarbonInterface $au): iterable;
}

<?php

namespace App\Etl\Contracts;

use App\Etl\Dto\CompteDskDto;
use App\Etl\Dto\DepartementDsk;
use App\Etl\Dto\MinutesCompteDsk;
use App\Etl\Dto\SalarieDsk;
use Carbon\CarbonInterface;

/** Lecture des données DSK (pointage RH) nécessaires au calcul. */
interface ConnecteurDsk extends ConnecteurSource
{
    /** @return iterable<DepartementDsk> */
    public function departements(): iterable;

    /** @return iterable<CompteDskDto> référentiel des compteurs (noAccount, libellé, type) */
    public function comptes(): iterable;

    /**
     * Salariés valides sur la période (un salarié parti avant `du` n'apparaît pas).
     *
     * @return iterable<SalarieDsk>
     */
    public function salaries(CarbonInterface $du, CarbonInterface $au): iterable;

    /**
     * Minutes calculées par salarié, par jour et par compteur sur [du, au].
     *
     * @return iterable<MinutesCompteDsk>
     */
    public function minutesParJour(CarbonInterface $du, CarbonInterface $au): iterable;
}

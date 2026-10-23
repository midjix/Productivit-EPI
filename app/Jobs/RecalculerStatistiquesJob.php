<?php

namespace App\Jobs;

use App\Services\ConsolidationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalcul du cache mensuel sur une période, en file d'attente.
 *
 * Déclenché après une qualification (compteur DSK, type de suivi) ou à la demande
 * de l'Administrateur. Ne lit que les flux bruts déjà importés : aucun accès aux sources.
 */
class RecalculerStatistiquesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly CarbonImmutable $du,
        public readonly CarbonImmutable $au,
    ) {}

    public function handle(ConsolidationService $consolidation): void
    {
        $consolidation->recalculerPeriode($this->du, $this->au);
    }
}

<?php

namespace App\Console\Commands;

use App\Services\ConsolidationService;
use App\Services\ReferentielService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Recalcul du cache mensuel à partir des flux bruts déjà importés (sans accès aux sources).
 *
 *   php artisan epi:recalculer                           toute la période couverte par les données
 *   php artisan epi:recalculer --from=2026-01-01 --to=2026-03-31
 */
class RecalculerStatistiques extends Command
{
    protected $signature = 'epi:recalculer
        {--from= : début de période (AAAA-MM-JJ), défaut : première donnée importée}
        {--to= : fin de période (AAAA-MM-JJ), défaut : dernière donnée importée}';

    protected $description = 'Recalcule les statistiques mensuelles (cache) depuis les flux bruts importés';

    public function handle(ConsolidationService $consolidation, ReferentielService $referentiel): int
    {
        [$duDonnees, $auDonnees] = $referentiel->periodeDesDonnees() ?? [null, null];

        $du = $this->option('from') ? CarbonImmutable::parse($this->option('from')) : $duDonnees;
        $au = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : $auDonnees;

        if ($du === null || $au === null) {
            $this->warn('Aucune donnée importée : rien à recalculer.');

            return self::SUCCESS;
        }

        $n = $consolidation->recalculerPeriode($du, $au);
        $this->info(sprintf('Statistiques mensuelles recalculées du %s au %s : %d lignes', $du->toDateString(), $au->toDateString(), $n));

        return self::SUCCESS;
    }
}

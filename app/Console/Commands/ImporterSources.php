<?php

namespace App\Console\Commands;

use App\Enums\StatutImport;
use App\Models\Import;
use App\Services\EtlService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Point d'entrée de l'import : lancé par le planificateur (chaque nuit) ou à la main.
 *
 *   php artisan epi:import                       fenêtre glissante, DSK + EpiLogg + recalcul
 *   php artisan epi:import --source=epilogg      une seule source
 *   php artisan epi:import --from=2026-01-01 --to=2026-03-31   période explicite (reprise d'historique)
 */
class ImporterSources extends Command
{
    protected $signature = 'epi:import
        {--source=all : dsk | epilogg | all}
        {--from= : début de période (AAAA-MM-JJ), défaut : aujourd\'hui − fenêtre}
        {--to= : fin de période (AAAA-MM-JJ), défaut : aujourd\'hui}';

    protected $description = 'Importe les flux DSK / EpiLogg (lecture seule) et recalcule les statistiques mensuelles';

    public function handle(EtlService $etl): int
    {
        $au = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : CarbonImmutable::today();
        $du = $this->option('from')
            ? CarbonImmutable::parse($this->option('from'))
            : $au->subDays((int) config('epi.import_fenetre_jours', 60));

        $this->info(sprintf('Import %s du %s au %s', $this->option('source'), $du->toDateString(), $au->toDateString()));

        try {
            $imports = match ($this->option('source')) {
                'dsk' => ['dsk' => $etl->importerDsk($du, $au)],
                'epilogg' => ['epilogg' => $etl->importerEpilogg($du, $au)],
                default => $etl->importerTout($du, $au),
            };
        } catch (Throwable $e) {
            $this->error('Import interrompu : '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($imports as $cle => $resultat) {
            if ($resultat instanceof Import) {
                $this->afficher($resultat);
            } else {
                $this->line("Statistiques mensuelles recalculées : {$resultat}");
            }
        }

        if ($this->option('source') !== 'all') {
            $stats = app(\App\Services\ConsolidationService::class)->recalculerPeriode($du, $au);
            $this->line("Statistiques mensuelles recalculées : {$stats}");
        }

        return self::SUCCESS;
    }

    private function afficher(Import $import): void
    {
        $etat = $import->statut === StatutImport::Succes ? '<info>succès</info>' : '<error>échec</error>';
        $this->line(sprintf('%s : %s — %d lignes (%s)', strtoupper($import->source->value), $etat, $import->nb_lignes, $import->message));
    }
}

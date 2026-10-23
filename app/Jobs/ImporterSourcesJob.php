<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ConsolidationService;
use App\Services\EtlService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Import manuel déclenché par l'Administrateur depuis l'API.
 *
 * Un import peut durer plusieurs minutes (VPN, dizaines de milliers de lignes) :
 * il est mis en file d'attente et l'API répond immédiatement (202). L'Administrateur
 * suit l'avancement dans le journal `imports` (statut en_cours → succès / échec).
 * Jamais deux imports en parallèle (même verrou que le planificateur nocturne).
 */
class ImporterSourcesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $source,
        public readonly CarbonImmutable $du,
        public readonly CarbonImmutable $au,
        public readonly ?int $declencheurId = null,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('epi-import'))->expireAfter(3600)];
    }

    public function handle(EtlService $etl, ConsolidationService $consolidation): void
    {
        $declencheur = $this->declencheurId !== null ? User::find($this->declencheurId) : null;

        match ($this->source) {
            'dsk' => $etl->importerDsk($this->du, $this->au, $declencheur),
            'epilogg' => $etl->importerEpilogg($this->du, $this->au, $declencheur),
            default => $etl->importerTout($this->du, $this->au, $declencheur),
        };

        // importerTout consolide déjà ; une source seule doit aussi rafraîchir le cache.
        if ($this->source !== 'all') {
            $consolidation->recalculerPeriode($this->du, $this->au);
        }
    }
}

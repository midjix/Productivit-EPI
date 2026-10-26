<?php

namespace App\Etl;

use App\Models\RegleConversion;
use Illuminate\Support\Collection;

/**
 * Barème historisé en mémoire : pour un tarif et une date, la valeur en minutes
 * d'un forfait selon la règle en vigueur à cette date.
 *
 * Les règles sont chargées une seule fois par exécution (consolidation ou calcul
 * détaillé), puis consultées des milliers de fois sans requête.
 */
final class Bareme
{
    /** @var Collection<int, Collection<int, RegleConversion>> règles par tarif, triées par date de début */
    private Collection $reglesParTarif;

    public function __construct()
    {
        $this->reglesParTarif = RegleConversion::query()
            ->orderBy('date_debut_validite')
            ->get()
            ->groupBy('tarif_id');
    }

    /** Minutes d'un forfait du tarif à la date donnée ; NULL si aucune règle ne couvre la date. */
    public function minutes(int $tarifId, string $date): ?int
    {
        $regle = $this->reglesParTarif->get($tarifId, collect())->first(fn (RegleConversion $r) => $r->couvre($date));

        return $regle?->minutes;
    }

    /** Valeur en minutes (décimal exact) d'une quantité de forfaits à une date ; NULL sans barème. */
    public function valoriser(int $tarifId, string $date, string $quantite): ?string
    {
        $minutes = $this->minutes($tarifId, $date);

        return $minutes === null ? null : bcmul($quantite, (string) $minutes, 2);
    }
}

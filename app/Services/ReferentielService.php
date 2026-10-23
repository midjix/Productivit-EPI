<?php

namespace App\Services;

use App\Jobs\RecalculerStatistiquesJob;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qualification des référentiels par l'Administrateur.
 *
 * Les imports créent les compteurs DSK (« autre », hors total) et les types de suivi
 * EpiLogg (tarif déduit du rate_name, ou aucun) ; l'Administrateur les qualifie ici,
 * et ses choix ne sont jamais écrasés par l'import suivant.
 *
 * Toute qualification change le résultat des mois déjà importés : le cache est
 * recalculé sur la période réellement concernée (premier → dernier flux brut du
 * compteur ou du type), en file d'attente.
 */
class ReferentielService
{
    /**
     * @param  array<string, mixed>  $attributs  categorie, compte_effectif, actif
     * @return array{du: string, au: string}|null période dont le recalcul a été planifié
     */
    public function qualifierCompteDsk(CompteDsk $compte, array $attributs): ?array
    {
        $compte->fill($attributs)->save();

        return $this->planifierRecalcul(DonneeDsk::query()->where('compte_dsk_id', $compte->id));
    }

    /**
     * @param  array<string, mixed>  $attributs  tarif_id, service_id, actif
     * @return array{du: string, au: string}|null
     */
    public function qualifierTypeSuivi(CategorieSuivi $type, array $attributs): ?array
    {
        $type->fill($attributs)->save();

        return $this->planifierRecalcul(DonneeEpilogg::query()->where('categorie_suivi_id', $type->id));
    }

    /**
     * Période couverte par l'ensemble des flux bruts (pour un recalcul complet).
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function periodeDesDonnees(): ?array
    {
        $bornes = array_filter([
            DonneeDsk::min('date'), DonneeDsk::max('date'),
            DonneeEpilogg::min('date'), DonneeEpilogg::max('date'),
        ]);

        if ($bornes === []) {
            return null;
        }

        return [
            CarbonImmutable::parse(min($bornes))->startOfMonth(),
            CarbonImmutable::parse(max($bornes))->endOfMonth(),
        ];
    }

    /**
     * @param  Builder<DonneeDsk|DonneeEpilogg>  $donnees
     * @return array{du: string, au: string}|null
     */
    private function planifierRecalcul(Builder $donnees): ?array
    {
        $min = (clone $donnees)->min('date');
        $max = (clone $donnees)->max('date');

        if ($min === null || $max === null) {
            return null;
        }

        $du = CarbonImmutable::parse($min)->startOfMonth();
        $au = CarbonImmutable::parse($max)->endOfMonth();

        RecalculerStatistiquesJob::dispatch($du, $au);

        return ['du' => $du->toDateString(), 'au' => $au->toDateString()];
    }
}

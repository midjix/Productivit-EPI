<?php

namespace App\Services;

use App\Models\RegleConversion;
use App\Models\Tarif;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gestion de la matrice de conversion HISTORISÉE.
 *
 * Règle d'or : une règle n'est jamais modifiée. Changer un barème =
 *   1. clôturer la règle ouverte du tarif (date_fin = veille de la date d'application),
 *   2. créer la nouvelle règle (date_fin NULL),
 *   3. recalculer le cache à partir de la date d'application,
 * le tout dans une transaction : soit tout est appliqué, soit rien.
 */
class MatriceConversionService
{
    public function __construct(private readonly ConsolidationService $consolidation) {}

    public function changerBareme(
        Tarif $tarif,
        int $minutes,
        CarbonInterface $dateDebut,
        ?User $auteur = null,
        ?string $commentaire = null,
    ): RegleConversion {
        if ($minutes <= 0) {
            throw new InvalidArgumentException(__('epi.matrice.minutes_positives'));
        }

        $debut = CarbonImmutable::parse($dateDebut)->startOfDay();

        return DB::transaction(function () use ($tarif, $minutes, $debut, $auteur, $commentaire) {
            /** @var RegleConversion|null $ouverte */
            $ouverte = $tarif->reglesConversion()->ouvertes()->lockForUpdate()->first();

            if ($ouverte !== null) {
                if ($debut->lte($ouverte->date_debut_validite)) {
                    throw new InvalidArgumentException(__('epi.matrice.date_posterieure', [
                        'date' => $debut->toDateString(),
                        'debut' => $ouverte->date_debut_validite->toDateString(),
                    ]));
                }

                $ouverte->update(['date_fin_validite' => $debut->subDay()]);
            }

            $nouvelle = $tarif->reglesConversion()->create([
                'minutes' => $minutes,
                'date_debut_validite' => $debut,
                'date_fin_validite' => null,
                'commentaire' => $commentaire,
                'created_by' => $auteur?->id,
            ]);

            $this->consolidation->recalculerDepuis($debut);

            return $nouvelle;
        });
    }
}

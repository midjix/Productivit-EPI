<?php

namespace App\Http\Resources;

use App\Models\StatistiqueMensuelle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un mois du cache, tel que la SPA le reçoit.
 * Les minutes DSK sont des entiers, les prestations et ratios des décimaux
 * sérialisés en chaînes (exactitude : « 0.8367 », jamais 0.83670000001).
 *
 * @mixin StatistiqueMensuelle
 */
class StatistiqueMensuelleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'annee' => $this->annee,
            'mois' => $this->mois,
            'minutes_total' => $this->minutes_total,
            'minutes_normales' => $this->minutes_normales,
            'minutes_conge' => $this->minutes_conge,
            'minutes_maladie' => $this->minutes_maladie,
            'minutes_formation' => $this->minutes_formation,
            'minutes_supervision' => $this->minutes_supervision,
            'minutes_deplacement' => $this->minutes_deplacement,
            'minutes_autres' => $this->minutes_autres,
            'nb_prestations' => $this->nb_prestations,
            'minutes_prestations_facturables' => $this->minutes_prestations_facturables,
            'minutes_activite_interne' => $this->minutes_activite_interne,
            'minutes_dispo_epi' => $this->minutes_dispo_epi,
            'minutes_dispo_client' => $this->minutes_dispo_client,
            'ratio' => $this->ratio,
            'ratio_brut' => $this->ratio_brut,
            'objectif_atteint' => $this->atteintObjectif(),
            'calcule_le' => $this->calcule_le?->toIso8601String(),
        ];
    }
}

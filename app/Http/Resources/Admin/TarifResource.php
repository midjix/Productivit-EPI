<?php

namespace App\Http\Resources\Admin;

use App\Models\RegleConversion;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un tarif avec sa matrice historisée (règles de la plus récente à la plus ancienne).
 *
 * @mixin Tarif
 */
class TarifResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $ouverte = $this->relationLoaded('reglesConversion')
            ? $this->reglesConversion->first(fn (RegleConversion $r) => $r->estOuverte())
            : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'facturable' => $this->facturable,
            'actif' => $this->actif,
            'minutes_en_vigueur' => $ouverte?->minutes,
            'nb_types_suivi' => $this->whenCounted('categoriesSuivi'),
            'regles' => $this->whenLoaded('reglesConversion', fn () => RegleConversionResource::collection($this->reglesConversion)),
        ];
    }
}

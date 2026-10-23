<?php

namespace App\Http\Resources\Admin;

use App\Models\CategorieSuivi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CategorieSuivi */
class TypeSuiviResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'epilogg_attend_type_id' => $this->epilogg_attend_type_id,
            'qualifie' => $this->tarif_id !== null,
            'tarif' => $this->whenLoaded('tarif', fn () => $this->tarif === null ? null : [
                'id' => $this->tarif->id,
                'code' => $this->tarif->code,
                'libelle' => $this->tarif->libelle,
                'facturable' => $this->tarif->facturable,
            ]),
            'service' => $this->whenLoaded('service', fn () => $this->service === null ? null : [
                'id' => $this->service->id,
                'code' => $this->service->code,
                'nom' => $this->service->nom,
            ]),
            'actif' => $this->actif,
            'nb_prestations' => $this->whenCounted('donneesEpilogg'),
        ];
    }
}

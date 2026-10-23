<?php

namespace App\Http\Resources\Admin;

use App\Models\RegleConversion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RegleConversion */
class RegleConversionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tarif_id' => $this->tarif_id,
            'minutes' => $this->minutes,
            'date_debut_validite' => $this->date_debut_validite?->toDateString(),
            'date_fin_validite' => $this->date_fin_validite?->toDateString(),
            'en_vigueur' => $this->estOuverte(),
            'commentaire' => $this->commentaire,
            'auteur' => $this->whenLoaded('auteur', fn () => $this->auteur?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

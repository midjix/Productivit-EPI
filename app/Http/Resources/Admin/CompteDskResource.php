<?php

namespace App\Http\Resources\Admin;

use App\Models\CompteDsk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompteDsk */
class CompteDskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'no_account' => $this->no_account,
            'libelle' => $this->libelle,
            'type_dsk' => $this->type_dsk,
            'categorie' => $this->categorie->value,
            'categorie_label' => $this->categorie->label(),
            'compte_effectif' => $this->compte_effectif,
            'actif' => $this->actif,
            'nb_lignes' => $this->whenCounted('donnees'),
        ];
    }
}

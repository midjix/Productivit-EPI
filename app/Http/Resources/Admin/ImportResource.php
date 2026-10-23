<?php

namespace App\Http\Resources\Admin;

use App\Enums\StatutImport;
use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une exécution d'import pour l'écran de supervision : résumé structuré en cas de
 * succès (cas à traiter : sans correspondance, non qualifiés…), message en cas d'échec.
 *
 * @mixin Import
 */
class ImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $succes = $this->statut === StatutImport::Succes;
        $resume = $succes && $this->message !== null ? json_decode($this->message, true) : null;

        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'statut' => $this->statut->value,
            'periode_debut' => $this->periode_debut?->toDateString(),
            'periode_fin' => $this->periode_fin?->toDateString(),
            'nb_lignes' => $this->nb_lignes,
            'resume' => is_array($resume) ? $resume : null,
            'erreur' => $this->statut === StatutImport::Echec ? $this->message : null,
            'declencheur' => $this->declencheur?->name ?? 'Planificateur',
            'demarre_le' => $this->demarre_le?->toIso8601String(),
            'termine_le' => $this->termine_le?->toIso8601String(),
            'duree_secondes' => $this->termine_le !== null && $this->demarre_le !== null
                ? (int) $this->demarre_le->diffInSeconds($this->termine_le)
                : null,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Identité minimale d'un salarié dans les écrans de statistiques (RGPD : ni email,
 * ni matricule, ni identifiants des sources — seulement de quoi l'afficher).
 *
 * @mixin User
 */
class SalarieResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'service_id' => $this->service_id,
        ];
    }
}

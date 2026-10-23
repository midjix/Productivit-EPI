<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue Administrateur d'un utilisateur (organigramme et affectations).
 * Plus complète que UserResource (rôle, activité, dates, appariement) mais toujours
 * sans identifiants techniques des sources ni mot de passe.
 *
 * @mixin User
 */
class UtilisateurAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'matricule' => $this->matricule,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'service' => $this->whenLoaded('service', fn () => $this->service === null ? null : [
                'id' => $this->service->id,
                'code' => $this->service->code,
                'nom' => $this->service->nom,
            ]),
            'actif' => $this->actif,
            'peut_se_connecter' => $this->peutSeConnecter(),
            'date_entree' => $this->date_entree?->toDateString(),
            'date_sortie' => $this->date_sortie?->toDateString(),
            'apparie_dsk' => $this->dsk_no_pers !== null,
            'apparie_epilogg' => $this->epilogg_user_id !== null,
        ];
    }
}

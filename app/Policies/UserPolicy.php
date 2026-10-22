<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Périmètre de consultation des données d'un salarié (RGPD : contrôle d'accès strict).
 *
 * Le middleware `role:` répond à « quel niveau de droits ? » ; la policy répond à
 * « sur QUELLES données ? » :
 *   - un Salarié ne voit que les siennes ;
 *   - un Coordinateur voit celles des salariés de SON service ;
 *   - un Administrateur voit tout.
 */
class UserPolicy
{
    public function voirStatistiques(User $auteur, User $cible): Response
    {
        if ($auteur->is($cible) || $auteur->estAdministrateur()) {
            return Response::allow();
        }

        if ($auteur->estCoordinateur() && $auteur->service_id !== null && $auteur->service_id === $cible->service_id) {
            return Response::allow();
        }

        return Response::deny("Vous n'avez pas accès aux données de ce salarié.");
    }
}

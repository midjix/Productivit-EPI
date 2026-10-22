<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Périmètre de consultation des statistiques agrégées d'un service :
 * le Coordinateur de ce service, ou un Administrateur.
 */
class ServicePolicy
{
    public function voirStatistiques(User $auteur, Service $service): Response
    {
        if ($auteur->estAdministrateur()) {
            return Response::allow();
        }

        if ($auteur->estCoordinateur() && $auteur->service_id === $service->id) {
            return Response::allow();
        }

        return Response::deny("Vous n'avez pas accès aux statistiques de ce service.");
    }
}

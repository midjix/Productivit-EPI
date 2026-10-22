<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle d'accès par rôle, avec héritage de droits :
 * `role:coordinateur` laisse passer un Coordinateur ET un Administrateur.
 *
 * S'utilise APRÈS `auth:sanctum` (un utilisateur non authentifié reçoit 401
 * avant d'arriver ici ; un utilisateur authentifié sans le rôle reçoit 403).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $requis = Role::tryFrom($role) ?? abort(500, "Rôle inconnu dans la définition de route : {$role}");

        if (! $request->user()?->aRole($requis)) {
            abort(403, 'Droits insuffisants pour cette action.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Services visibles par l'utilisateur, pour alimenter les filtres de la SPA :
 * un Coordinateur ne voit que le sien, un Administrateur tous les services actifs.
 */
class ServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $services = Service::query()
            ->where('actif', true)
            ->when(! $user->estAdministrateur(), fn ($q) => $q->whereKey($user->service_id))
            ->orderBy('nom')
            ->get();

        return ServiceResource::collection($services);
    }
}

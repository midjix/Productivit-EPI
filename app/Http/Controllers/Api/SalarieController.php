<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalarieResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Salariés consultables par l'utilisateur, pour la barre de recherche de la SPA :
 * un Coordinateur voit les salariés actifs de son service, un Administrateur tous.
 * Liste courte (quelques dizaines de personnes) : le filtrage se fait côté client.
 */
class SalarieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $salaries = User::query()
            ->with('service:id,code,nom')
            ->where('actif', true)
            ->when(! $user->estAdministrateur(), fn ($q) => $q->where('service_id', $user->service_id))
            ->orderBy('name')
            ->get();

        return SalarieResource::collection($salaries);
    }
}

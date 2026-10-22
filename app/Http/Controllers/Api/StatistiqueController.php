<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PeriodeRequest;
use App\Http\Resources\SalarieResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\StatistiqueMensuelleResource;
use App\Models\Service;
use App\Models\User;
use App\Services\StatistiquesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * API de consultation — lecture du cache `statistiques_mensuelles` uniquement.
 *
 * Contrôleur mince : validation (PeriodeRequest) + autorisation (policies) +
 * délégation au StatistiquesService. Aucun calcul ici.
 */
class StatistiqueController extends Controller
{
    public function __construct(private readonly StatistiquesService $statistiques) {}

    /** Tableau de bord de l'utilisateur connecté (tout rôle). */
    public function moi(PeriodeRequest $request): JsonResponse
    {
        return $this->salarie($request, $request->user());
    }

    /** Tableau de bord d'un salarié : lui-même, son coordinateur ou un administrateur (UserPolicy). */
    public function salarie(PeriodeRequest $request, User $salarie): JsonResponse
    {
        Gate::authorize('voirStatistiques', $salarie);

        $mois = $this->statistiques->mensuelles($salarie, $request->annee());

        return response()->json([
            'salarie' => new SalarieResource($salarie),
            'annee' => $request->annee(),
            'annees_disponibles' => $this->statistiques->anneesDisponibles($salarie),
            'mensuelles' => StatistiqueMensuelleResource::collection($mois),
            'synthese' => $this->statistiques->synthese($mois),
        ]);
    }

    /** Statistiques agrégées d'un service : son coordinateur ou un administrateur (ServicePolicy). */
    public function service(PeriodeRequest $request, Service $service): JsonResponse
    {
        Gate::authorize('voirStatistiques', $service);

        $resultat = $this->statistiques->pourService($service, $request->annee(), $request->mois());

        return response()->json([
            'service' => new ServiceResource($service),
            'annee' => $request->annee(),
            'mois' => $request->mois(),
            'salaries' => $resultat['salaries']->map(fn (array $s) => [
                'salarie' => new SalarieResource($s['salarie']),
                'synthese' => $s['synthese'],
            ]),
            'synthese' => $resultat['synthese'],
        ]);
    }

    /** Vue « Total EPI » par service : administrateur uniquement (middleware role:administrateur). */
    public function globale(PeriodeRequest $request): JsonResponse
    {
        $resultat = $this->statistiques->globale($request->annee(), $request->mois());

        return response()->json([
            'annee' => $request->annee(),
            'mois' => $request->mois(),
            'services' => $resultat['services']->map(fn (array $s) => [
                'service' => $s['service'] === null ? null : new ServiceResource($s['service']),
                'synthese' => $s['synthese'],
            ]),
            'synthese' => $resultat['synthese'],
        ]);
    }
}

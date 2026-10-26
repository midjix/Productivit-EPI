<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DetailRequest;
use App\Models\CategorieSuivi;
use App\Models\DonneeEpilogg;
use App\Models\Service;
use App\Models\Tarif;
use App\Models\User;
use App\Services\DetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Écran « Détail » (niveau de détail du fichier Excel de la Fondation) :
 * calcul à la demande depuis les flux bruts, périmètre soumis aux policies.
 */
class DetailController extends Controller
{
    public function __construct(private readonly DetailService $detail) {}

    public function show(DetailRequest $request): JsonResponse
    {
        $perimetre = $request->validated('perimetre');
        $id = $request->validated('id') !== null ? (int) $request->validated('id') : null;
        $libelle = $this->autoriser($request->user(), $perimetre, $id);

        $resultat = $this->detail->calculer($perimetre, $id, $request->du(), $request->au(), $request->typesSuivi(), $request->typesPrestation());

        return response()->json([
            'perimetre' => ['type' => $perimetre, 'id' => $id, 'libelle' => $libelle],
            'du' => $request->du()->toDateString(),
            'au' => $request->au()->toDateString(),
            'filtres' => ['types_suivi' => $request->typesSuivi(), 'types_prestation' => $request->typesPrestation()],
        ] + $resultat);
    }

    /** Valeurs possibles des filtres pour l'utilisateur connecté. */
    public function filtres(Request $request): JsonResponse
    {
        $user = $request->user();

        $services = Service::query()->where('actif', true)
            ->when(! $user->estAdministrateur(), fn ($q) => $q->whereKey($user->service_id))
            ->orderBy('nom')->get(['id', 'code', 'nom']);

        return response()->json([
            'types_suivi' => CategorieSuivi::query()->with('tarif:id,code')->where('actif', true)->orderBy('libelle')->get()
                ->map(fn (CategorieSuivi $c) => ['id' => $c->id, 'libelle' => $c->libelle, 'tarif_code' => $c->tarif?->code, 'facturable' => $c->estFacturable()])
                ->values(),
            'types_prestation' => DonneeEpilogg::query()->whereNotNull('type_prestation')->distinct()->orderBy('type_prestation')->pluck('type_prestation'),
            'tarifs' => $user->estAdministrateur()
                ? Tarif::query()->where('actif', true)->orderBy('code')->get(['id', 'code', 'libelle', 'facturable'])
                : [],
            'services' => $user->aRole(\App\Enums\Role::Coordinateur) ? $services : [],
            'perimetres' => array_values(array_filter(DetailService::PERIMETRES, fn ($p) => match ($p) {
                'salarie' => true,
                'service' => $user->aRole(\App\Enums\Role::Coordinateur),
                default => $user->estAdministrateur(),
            })),
        ]);
    }

    /** Contrôle du périmètre et libellé affiché. */
    private function autoriser(User $user, string $perimetre, ?int $id): string
    {
        switch ($perimetre) {
            case 'salarie':
                $cible = User::findOrFail($id);
                Gate::authorize('voirStatistiques', $cible);

                return $cible->name;
            case 'service':
                $service = Service::findOrFail($id);
                Gate::authorize('voirStatistiques', $service);

                return $service->nom;
            case 'tarif':
                abort_unless($user->estAdministrateur(), 403, __('epi.auth.droits_insuffisants'));
                $tarif = Tarif::findOrFail($id);

                return __('epi.detail.tarif', ['code' => $tarif->code]);
            default:
                abort_unless($user->estAdministrateur(), 403, __('epi.auth.droits_insuffisants'));

                return __('epi.detail.'.$perimetre);
        }
    }
}

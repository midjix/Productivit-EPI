<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QualifierTypeSuiviRequest;
use App\Http\Resources\Admin\TypeSuiviResource;
use App\Models\CategorieSuivi;
use App\Services\ReferentielService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Qualification des types de suivi EpiLogg : rattachement à un tarif (barème) et à un service. */
class TypeSuiviController extends Controller
{
    public function __construct(private readonly ReferentielService $referentiel) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate(['non_qualifies' => ['sometimes', 'boolean']]);

        $types = CategorieSuivi::query()
            ->with(['tarif:id,code,libelle,facturable', 'service:id,code,nom'])
            ->withCount('donneesEpilogg')
            ->when($filtres['non_qualifies'] ?? false, fn ($q) => $q->nonQualifies())
            ->orderBy('libelle')
            ->get();

        return TypeSuiviResource::collection($types);
    }

    public function update(QualifierTypeSuiviRequest $request, CategorieSuivi $type): JsonResponse
    {
        $recalcul = $this->referentiel->qualifierTypeSuivi($type, $request->validated());

        return response()->json([
            'type' => new TypeSuiviResource($type->load(['tarif', 'service'])->loadCount('donneesEpilogg')),
            'recalcul' => $recalcul,
        ]);
    }
}

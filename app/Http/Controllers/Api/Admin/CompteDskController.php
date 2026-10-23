<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QualifierCompteDskRequest;
use App\Http\Resources\Admin\CompteDskResource;
use App\Models\CompteDsk;
use App\Services\ReferentielService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Cas d'utilisation « Qualifier les compteurs DSK » (catégorie d'heures, entre dans le total ?). */
class CompteDskController extends Controller
{
    public function __construct(private readonly ReferentielService $referentiel) {}

    public function index(): AnonymousResourceCollection
    {
        return CompteDskResource::collection(
            CompteDsk::query()->withCount('donnees')->orderBy('no_account')->get(),
        );
    }

    /** Applique la qualification et planifie le recalcul des mois concernés. */
    public function update(QualifierCompteDskRequest $request, CompteDsk $compte): JsonResponse
    {
        $recalcul = $this->referentiel->qualifierCompteDsk($compte, $request->validated());

        return response()->json([
            'compte' => new CompteDskResource($compte->loadCount('donnees')),
            'recalcul' => $recalcul,
        ]);
    }
}

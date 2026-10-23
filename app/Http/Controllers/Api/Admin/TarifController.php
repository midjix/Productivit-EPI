<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangerBaremeRequest;
use App\Http\Resources\Admin\RegleConversionResource;
use App\Http\Resources\Admin\TarifResource;
use App\Models\Tarif;
use App\Services\MatriceConversionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Cas d'utilisation « Gérer la matrice de conversion » : lecture de l'historique, nouveau barème. */
class TarifController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tarifs = Tarif::query()
            ->with(['reglesConversion' => fn ($q) => $q->orderByDesc('date_debut_validite')->with('auteur:id,name')])
            ->withCount('categoriesSuivi')
            ->orderBy('code')
            ->get();

        return TarifResource::collection($tarifs);
    }

    /**
     * Nouveau barème = clôture de la règle en vigueur + création + recalcul, en transaction
     * (MatriceConversionService). Une incohérence temporelle devient une erreur de validation (422).
     */
    public function storeRegle(ChangerBaremeRequest $request, Tarif $tarif, MatriceConversionService $matrice): JsonResponse
    {
        try {
            $regle = $matrice->changerBareme(
                $tarif,
                (int) $request->validated('minutes'),
                CarbonImmutable::parse($request->validated('date_debut')),
                $request->user(),
                $request->validated('commentaire'),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['date_debut' => $e->getMessage()]);
        }

        return response()->json(new RegleConversionResource($regle->load('auteur:id,name')), 201);
    }
}

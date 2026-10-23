<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PeriodeTraitementRequest;
use App\Jobs\RecalculerStatistiquesJob;
use App\Services\ReferentielService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/** Cas d'utilisation « Recalculer les statistiques mensuelles » à la demande (sans ré-import). */
class RecalculController extends Controller
{
    public function store(PeriodeTraitementRequest $request, ReferentielService $referentiel): JsonResponse
    {
        [$duDonnees, $auDonnees] = $referentiel->periodeDesDonnees() ?? [null, null];

        $du = $request->du() ?? $duDonnees;
        $au = $request->au() ?? $auDonnees ?? CarbonImmutable::today();

        if ($du === null) {
            return response()->json(['message' => __('epi.admin.rien_a_recalculer')], 200);
        }

        RecalculerStatistiquesJob::dispatch($du, $au);

        return response()->json([
            'message' => __('epi.admin.recalcul_en_file'),
            'du' => $du->toDateString(),
            'au' => $au->toDateString(),
        ], 202);
    }
}

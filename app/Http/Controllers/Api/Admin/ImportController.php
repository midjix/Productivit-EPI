<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SourceImport;
use App\Enums\StatutImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PeriodeTraitementRequest;
use App\Http\Resources\Admin\ImportResource;
use App\Jobs\ImporterSourcesJob;
use App\Models\Import;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Supervision des imports (journal) et import manuel (cas d'utilisation « Importer les données »). */
class ImportController extends Controller
{
    /** Journal, du plus récent au plus ancien, filtrable par source et statut. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'source' => ['sometimes', Rule::enum(SourceImport::class)],
            'statut' => ['sometimes', Rule::enum(StatutImport::class)],
        ]);

        $imports = Import::query()
            ->with('declencheur:id,name')
            ->when($filtres['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->when($filtres['statut'] ?? null, fn ($q, $s) => $q->where('statut', $s))
            ->orderByDesc('demarre_le')
            ->orderByDesc('id')
            ->paginate(20);

        return ImportResource::collection($imports);
    }

    public function show(Import $import): ImportResource
    {
        return new ImportResource($import->load('declencheur:id,name'));
    }

    /** Met l'import en file d'attente et répond 202 : le journal rend compte de l'exécution. */
    public function store(PeriodeTraitementRequest $request): JsonResponse
    {
        $au = $request->au() ?? CarbonImmutable::today();
        $du = $request->du() ?? $au->subDays((int) config('epi.import_fenetre_jours', 60));

        ImporterSourcesJob::dispatch($request->source(), $du, $au, $request->user()->id);

        return response()->json([
            'message' => __('epi.admin.import_en_file'),
            'source' => $request->source(),
            'du' => $du->toDateString(),
            'au' => $au->toDateString(),
        ], 202);
    }
}

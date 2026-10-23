<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModifierServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

/** Organigramme : services (synchronisés depuis les départements DSK, libellé et activité ajustables). */
class ServiceAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $services = Service::query()->withCount('utilisateurs')->orderBy('nom')->get();

        return response()->json($services->map(fn (Service $s) => $this->representer($s)));
    }

    public function update(ModifierServiceRequest $request, Service $service): JsonResponse
    {
        $service->fill($request->validated())->save();

        return response()->json($this->representer($service->loadCount('utilisateurs')));
    }

    /** @return array<string, mixed> */
    private function representer(Service $service): array
    {
        return [
            'id' => $service->id,
            'code' => $service->code,
            'nom' => $service->nom,
            'actif' => $service->actif,
            'nb_utilisateurs' => $service->utilisateurs_count,
        ];
    }
}

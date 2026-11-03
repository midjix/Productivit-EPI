<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AutorisePerimetreDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\DetailRequest;
use App\Http\Requests\PeriodeRequest;
use App\Models\CategorieSuivi;
use App\Models\Service;
use App\Models\User;
use App\Services\DetailService;
use App\Services\ExportExcelService;
use App\Services\StatistiquesService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Cas d'utilisation « Exporter la vue courante en Excel » : mêmes requêtes, mêmes
 * policies et mêmes services que les endpoints JSON ; seule la mise en forme change.
 */
class ExportController extends Controller
{
    use AutorisePerimetreDetail;

    public function __construct(
        private readonly StatistiquesService $statistiques,
        private readonly DetailService $detail,
        private readonly ExportExcelService $excel,
    ) {}

    public function moi(PeriodeRequest $request): BinaryFileResponse
    {
        return $this->salarie($request, $request->user());
    }

    public function salarie(PeriodeRequest $request, User $salarie): BinaryFileResponse
    {
        Gate::authorize('voirStatistiques', $salarie);

        $mois = $this->statistiques->mensuelles($salarie, $request->annee());
        $periode = (string) $request->annee();
        $chemin = $this->excel->statistiques($salarie->name, $periode, $request->user(), $this->statistiques->synthese($mois), $mois);

        return $this->telecharger($chemin, ExportExcelService::nomFichier('efficacite', $salarie->name, $periode));
    }

    public function service(PeriodeRequest $request, Service $service): BinaryFileResponse
    {
        Gate::authorize('voirStatistiques', $service);

        $resultat = $this->statistiques->pourService($service, $request->annee(), $request->mois());
        $periode = $this->periode($request);
        $lignes = $resultat['salaries']->map(fn (array $s) => ['libelle' => $s['salarie']->name, 'detail' => $service->nom, 'synthese' => $s['synthese']])->all();
        $chemin = $this->excel->statistiques($service->nom, $periode, $request->user(), $resultat['synthese'], [], $lignes, __('epi.export.feuilles.salaries'));

        return $this->telecharger($chemin, ExportExcelService::nomFichier('efficacite', $service->nom, $periode));
    }

    public function globale(PeriodeRequest $request): BinaryFileResponse
    {
        $resultat = $this->statistiques->globale($request->annee(), $request->mois());
        $periode = $this->periode($request);
        $lignes = $resultat['services']->map(fn (array $s) => ['libelle' => $s['service']?->nom ?? __('epi.export.meta.sans_service'), 'detail' => $s['service']?->code, 'synthese' => $s['synthese']])->all();
        $chemin = $this->excel->statistiques(__('epi.detail.epi'), $periode, $request->user(), $resultat['synthese'], [], $lignes, __('epi.export.feuilles.services'));

        return $this->telecharger($chemin, ExportExcelService::nomFichier('efficacite', __('epi.detail.epi'), $periode));
    }

    public function detail(DetailRequest $request): BinaryFileResponse
    {
        $perimetre = $request->validated('perimetre');
        $id = $request->validated('id') !== null ? (int) $request->validated('id') : null;
        $libelle = $this->autoriserPerimetre($request->user(), $perimetre, $id);

        $resultat = $this->detail->calculer($perimetre, $id, $request->du(), $request->au(), $request->typesSuivi(), $request->typesPrestation());
        $filtres = [
            'types_suivi' => CategorieSuivi::query()->whereKey($request->typesSuivi())->orderBy('libelle')->pluck('libelle')->all(),
            'types_prestation' => $request->typesPrestation(),
        ];
        $chemin = $this->excel->detail($libelle, $request->du(), $request->au(), $request->user(), $resultat, $filtres);

        return $this->telecharger($chemin, ExportExcelService::nomFichier('detail', $libelle, $request->du()->toDateString().'-'.$request->au()->toDateString()));
    }

    /** « 2026 » ou « 2026-04 » : la période des vues de consultation. */
    private function periode(PeriodeRequest $request): string
    {
        return $request->mois() === null
            ? (string) $request->annee()
            : CarbonImmutable::create($request->annee(), $request->mois(), 1)->format('Y-m');
    }

    private function telecharger(string $chemin, string $nom): BinaryFileResponse
    {
        return response()
            ->download($chemin, $nom, ['Content-Type' => ExportExcelService::TYPE_MIME])
            ->deleteFileAfterSend(true);
    }
}

<?php

namespace App\Services;

use App\Etl\CalculEfficacite;
use App\Models\Service;
use App\Models\StatistiqueMensuelle;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Couche métier de la CONSULTATION : lit exclusivement le cache `statistiques_mensuelles`
 * (jamais les flux bruts, cf. CLAUDE.md : 5 ans d'historique à servir rapidement).
 *
 * Toute agrégation (année d'un salarié, service, Total EPI) repasse par `CalculEfficacite`
 * sur les SOMMES de minutes : l'efficacité d'un groupe est le rapport des totaux
 * (comme la feuille « Total EPI » de la Fondation), pas la moyenne des ratios —
 * un salarié à mi-temps ne pèse pas autant qu'un temps plein.
 */
class StatistiquesService
{
    /** Mois d'un salarié pour une année, dans l'ordre chronologique. @return Collection<int, StatistiqueMensuelle> */
    public function mensuelles(User $salarie, int $annee): Collection
    {
        return $salarie->statistiquesMensuelles()
            ->periode($annee)
            ->orderBy('mois')
            ->get();
    }

    /** Années pour lesquelles le salarié a au moins un mois calculé (filtre de l'interface). @return list<int> */
    public function anneesDisponibles(User $salarie): array
    {
        return $salarie->statistiquesMensuelles()
            ->distinct()
            ->orderByDesc('annee')
            ->pluck('annee')
            ->map(fn ($a) => (int) $a)
            ->all();
    }

    /**
     * Statistiques d'un service : une synthèse par salarié + la synthèse du service.
     *
     * @return array{salaries: Collection<int, array{salarie: User, synthese: array<string, mixed>}>, synthese: array<string, mixed>}
     */
    public function pourService(Service $service, int $annee, ?int $mois): array
    {
        $lignes = StatistiqueMensuelle::query()
            ->periode($annee, $mois)
            ->whereIn('user_id', User::query()->where('service_id', $service->id)->select('id'))
            ->with('utilisateur:id,name,service_id')
            ->get();

        $salaries = $lignes
            ->groupBy('user_id')
            ->map(fn (Collection $parSalarie) => [
                'salarie' => $parSalarie->first()->utilisateur,
                'synthese' => $this->synthese($parSalarie),
            ])
            ->sortBy(fn (array $s) => $s['salarie']->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return ['salaries' => $salaries, 'synthese' => $this->synthese($lignes)];
    }

    /**
     * Vue globale (Administrateur) : une synthèse par service + le « Total EPI ».
     *
     * @return array{services: Collection<int, array{service: ?Service, synthese: array<string, mixed>}>, synthese: array<string, mixed>}
     */
    public function globale(int $annee, ?int $mois): array
    {
        $lignes = StatistiqueMensuelle::query()
            ->periode($annee, $mois)
            ->with('utilisateur:id,service_id')
            ->get();

        $services = Service::query()->orderBy('nom')->get()->keyBy('id');

        $parService = $lignes
            ->groupBy(fn (StatistiqueMensuelle $l) => $l->utilisateur->service_id ?? 0)
            ->map(fn (Collection $l, int $serviceId) => [
                'service' => $services->get($serviceId),
                'synthese' => $this->synthese($l),
            ])
            ->sortBy(fn (array $s) => $s['service']?->nom ?? '~', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return ['services' => $parService, 'synthese' => $this->synthese($lignes)];
    }

    /**
     * Cascade d'efficacité appliquée aux sommes d'un ensemble de lignes du cache.
     *
     * @param  iterable<StatistiqueMensuelle>  $lignes
     * @return array<string, mixed>
     */
    public function synthese(iterable $lignes): array
    {
        $lignes = Collection::wrap($lignes);

        $somme = fn (string $colonne): int => (int) $lignes->sum($colonne);
        $sommeDecimale = fn (string $colonne): string => $lignes->reduce(
            fn (string $acc, StatistiqueMensuelle $l) => bcadd($acc, (string) $l->{$colonne}, 2),
            '0.00',
        );

        $calcul = new CalculEfficacite(
            minutesNormales: $somme('minutes_normales'),
            minutesConge: $somme('minutes_conge'),
            minutesMaladie: $somme('minutes_maladie'),
            minutesFormation: $somme('minutes_formation'),
            minutesSupervision: $somme('minutes_supervision'),
            minutesDeplacement: $somme('minutes_deplacement'),
            minutesAutres: $somme('minutes_autres'),
            nbPrestations: $sommeDecimale('nb_prestations'),
            minutesFacturables: $sommeDecimale('minutes_prestations_facturables'),
            minutesActiviteInterne: $sommeDecimale('minutes_activite_interne'),
        );

        $ratio = $calcul->ratio();
        $objectif = number_format((float) config('epi.objectif_efficacite'), 4, '.', '');

        return $calcul->attributs() + [
            'nb_mois' => $lignes->map(fn (StatistiqueMensuelle $l) => "{$l->annee}-{$l->mois}")->unique()->count(),
            'nb_salaries' => $lignes->pluck('user_id')->unique()->count(),
            'objectif' => $objectif,
            'objectif_atteint' => $ratio === null ? null : bccomp($ratio, $objectif, 4) >= 0,
        ];
    }
}

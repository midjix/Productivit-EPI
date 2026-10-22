<?php

namespace App\Services;

use App\Enums\CategorieHeure;
use App\Etl\CalculEfficacite;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\RegleConversion;
use App\Models\StatistiqueMensuelle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Alimente le cache `statistiques_mensuelles` à partir des flux bruts.
 *
 * C'est la SEULE source de calcul du cache : ni seeder, ni contrôleur n'y écrivent.
 * Le barème appliqué à chaque prestation est celui en vigueur À SA DATE (historisation) :
 * recalculer 2024 avec cette classe redonne les chiffres de 2024.
 */
class ConsolidationService
{
    /** @var Collection<int, Collection<int, RegleConversion>>|null règles par tarif, chargées une fois par exécution */
    private ?Collection $reglesParTarif = null;

    /**
     * Recalcule un ensemble de (salarié, année, mois).
     *
     * @param  iterable<array{user_id: int, annee: int, mois: int}>  $periodes
     * @return int nombre de lignes de cache écrites
     */
    public function recalculer(iterable $periodes): int
    {
        $this->reglesParTarif = null;
        $n = 0;

        foreach ($periodes as $p) {
            $this->recalculerMois((int) $p['user_id'], (int) $p['annee'], (int) $p['mois']);
            $n++;
        }

        return $n;
    }

    /** Tous les (salarié, mois) ayant des données brutes entre deux dates. */
    public function recalculerPeriode(CarbonInterface $du, CarbonInterface $au, ?iterable $userIds = null): int
    {
        return $this->recalculer($this->periodesTouchees($du, $au, $userIds));
    }

    /** Après un changement de barème : tous les mois à partir de la date d'application. */
    public function recalculerDepuis(CarbonInterface $date): int
    {
        $max = DonneeEpilogg::max('date');

        if ($max === null) {
            return 0;
        }

        return $this->recalculerPeriode($date, CarbonImmutable::parse($max)->endOfMonth());
    }

    public function recalculerMois(int $userId, int $annee, int $mois): StatistiqueMensuelle
    {
        $debut = CarbonImmutable::create($annee, $mois, 1)->startOfDay();
        $fin = $debut->endOfMonth();

        $calcul = new CalculEfficacite(
            ...$this->minutesDsk($userId, $debut, $fin),
            ...$this->prestations($userId, $debut, $fin),
        );

        return StatistiqueMensuelle::updateOrCreate(
            ['user_id' => $userId, 'annee' => $annee, 'mois' => $mois],
            $calcul->attributs() + ['calcule_le' => now()],
        );
    }

    // ---- Dénominateur : minutes DSK par catégorie ------------------------------

    /** @return array<string, int> arguments nommés de CalculEfficacite */
    private function minutesDsk(int $userId, CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $parCategorie = DonneeDsk::query()
            ->join('comptes_dsk', 'comptes_dsk.id', '=', 'donnees_dsk.compte_dsk_id')
            ->where('donnees_dsk.user_id', $userId)
            ->whereBetween('donnees_dsk.date', [$debut->toDateString(), $fin->toDateString()])
            ->where('comptes_dsk.compte_effectif', true)
            ->groupBy('comptes_dsk.categorie')
            ->selectRaw('comptes_dsk.categorie, SUM(donnees_dsk.minutes) AS minutes')
            ->pluck('minutes', 'categorie');

        $args = [];

        foreach (CategorieHeure::cases() as $categorie) {
            // minutes_normales -> minutesNormales (argument nommé du calcul)
            $arg = lcfirst(str_replace('_', '', ucwords($categorie->colonneStatistique(), '_')));
            $args[$arg] = max(0, (int) ($parCategorie[$categorie->value] ?? 0));
        }

        return $args;
    }

    // ---- Numérateur : prestations × barème en vigueur à leur date ----------------

    /** @return array{nbPrestations: string, minutesFacturables: string, minutesActiviteInterne: string} */
    private function prestations(int $userId, CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $lignes = DonneeEpilogg::query()
            ->join('categories_suivi', 'categories_suivi.id', '=', 'donnees_epilogg.categorie_suivi_id')
            ->join('tarifs', 'tarifs.id', '=', 'categories_suivi.tarif_id')
            ->where('donnees_epilogg.user_id', $userId)
            ->whereBetween('donnees_epilogg.date', [$debut->toDateString(), $fin->toDateString()])
            ->select(['donnees_epilogg.date', 'donnees_epilogg.quantite', 'tarifs.id AS tarif_id', 'tarifs.facturable'])
            ->get();

        $nb = '0.00';
        $facturables = '0.00';
        $internes = '0.00';

        foreach ($lignes as $l) {
            $regle = $this->regleEnVigueur((int) $l->tarif_id, $l->date);

            if ($regle === null) {
                continue; // pas de barème à cette date : la prestation n'est pas valorisée (jamais inventée)
            }

            $quantite = (string) $l->quantite;
            $minutes = bcmul($quantite, (string) $regle->minutes, 2);
            $nb = bcadd($nb, $quantite, 2);

            if ($l->facturable) {
                $facturables = bcadd($facturables, $minutes, 2);
            } else {
                $internes = bcadd($internes, $minutes, 2);
            }
        }

        return ['nbPrestations' => $nb, 'minutesFacturables' => $facturables, 'minutesActiviteInterne' => $internes];
    }

    private function regleEnVigueur(int $tarifId, string $date): ?RegleConversion
    {
        $this->reglesParTarif ??= RegleConversion::query()
            ->orderBy('date_debut_validite')
            ->get()
            ->groupBy('tarif_id');

        return $this->reglesParTarif->get($tarifId, collect())->first(fn (RegleConversion $r) => $r->couvre($date));
    }

    // ---- Périodes -------------------------------------------------------------

    /** @return Collection<int, array{user_id: int, annee: int, mois: int}> */
    private function periodesTouchees(CarbonInterface $du, CarbonInterface $au, ?iterable $userIds): Collection
    {
        $ids = $userIds === null ? null : collect($userIds)->all();
        $bornes = [$du->toDateString(), $au->toDateString()];

        $dsk = DonneeDsk::query()->whereBetween('date', $bornes)
            ->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids))
            ->selectRaw('user_id, YEAR(date) AS annee, MONTH(date) AS mois')->distinct()->get();

        $epi = DonneeEpilogg::query()->whereBetween('date', $bornes)
            ->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids))
            ->selectRaw('user_id, YEAR(date) AS annee, MONTH(date) AS mois')->distinct()->get();

        return $dsk->concat($epi)
            ->map(fn ($r) => ['user_id' => (int) $r->user_id, 'annee' => (int) $r->annee, 'mois' => (int) $r->mois])
            ->unique(fn ($p) => "{$p['user_id']}-{$p['annee']}-{$p['mois']}")
            ->values();
    }
}

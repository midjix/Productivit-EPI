<?php

namespace App\Services;

use App\Etl\Contracts\ConnecteurDsk;
use App\Etl\Contracts\ConnecteurEpilogg;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\Service;
use App\Models\Tarif;
use App\Models\User;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Diagnostic LECTURE SEULE des sources avant un import réel (« répétition générale »).
 *
 * Répond aux questions que l'on se pose le jour où l'accès à une source est ouvert :
 * est-elle joignable ? que renvoie-t-elle sur la période ? les salariés DSK et les
 * comptes EpiLogg s'apparient-ils par email ? quels référentiels devront être qualifiés
 * par l'Administrateur après l'import ?
 *
 * Il parcourt exactement les mêmes méthodes de connecteur que l'EtlService, mais
 * n'écrit RIEN : ni dans les sources (contrainte absolue), ni dans la base locale.
 * La base locale est seulement lue pour comparer ce qui arrive avec ce qui existe.
 */
class DiagnosticSourcesService
{
    /** Au-delà, les listes nominatives sont tronquées (lisibilité et minimisation). */
    public const MAX_EXEMPLES = 20;

    public function __construct(
        private readonly ConnecteurDsk $dsk,
        private readonly ConnecteurEpilogg $epilogg,
    ) {}

    /**
     * @param  'all'|'dsk'|'epilogg'  $source
     * @return array{
     *     periode: array{du: string, au: string},
     *     dsk: ?array<string, mixed>,
     *     epilogg: ?array<string, mixed>,
     *     appariement: ?array<string, mixed>,
     *     pret: bool
     * }
     */
    public function verifier(CarbonInterface $du, CarbonInterface $au, string $source = 'all'): array
    {
        $dsk = in_array($source, ['all', 'dsk'], true) ? $this->verifierDsk($du, $au) : null;
        $epilogg = in_array($source, ['all', 'epilogg'], true) ? $this->verifierEpilogg($du, $au) : null;

        $appariement = ($dsk !== null && $dsk['ok'] && $epilogg !== null && $epilogg['ok'])
            ? $this->apparier($dsk['_emails'], $epilogg['_emails'])
            : null;

        unset($dsk['_emails'], $epilogg['_emails']);

        return [
            'periode' => ['du' => $du->toDateString(), 'au' => $au->toDateString()],
            'dsk' => $dsk,
            'epilogg' => $epilogg,
            'appariement' => $appariement,
            'pret' => ($dsk === null || $dsk['ok']) && ($epilogg === null || $epilogg['ok']),
        ];
    }

    /** @return array<string, mixed> */
    private function verifierDsk(CarbonInterface $du, CarbonInterface $au): array
    {
        $debut = hrtime(true);

        try {
            $this->dsk->verifierConnexion();

            $codesConnus = Service::pluck('code')->map(fn ($c) => (string) $c)->all();
            $departements = 0;
            $departementsNouveaux = [];
            foreach ($this->dsk->departements() as $d) {
                $departements++;
                if (! in_array($d->codeDept, $codesConnus, true)) {
                    $departementsNouveaux[] = $d->codeDept.' ('.$d->libelle.')';
                }
            }

            $comptesConnus = CompteDsk::pluck('no_account')->map(fn ($n) => (int) $n)->all();
            $comptes = 0;
            $comptesNouveaux = [];
            foreach ($this->dsk->comptes() as $c) {
                $comptes++;
                if (! in_array($c->noAccount, $comptesConnus, true)) {
                    $comptesNouveaux[] = $c->noAccount.' – '.$c->libelle;
                }
            }

            $salaries = 0;
            $sansEmail = [];
            $emails = [];
            $noPers = [];
            foreach ($this->dsk->salaries($du, $au) as $s) {
                $salaries++;
                $noPers[$s->noPers] = true;
                $email = $s->emailNormalise();
                if ($email === null) {
                    $sansEmail[] = $s->nomComplet.' (noPers '.$s->noPers.')';
                } else {
                    $emails[$email] = $s->nomComplet;
                }
            }

            $minutes = 0;
            $inconnus = [];
            foreach ($this->dsk->minutesParJour($du, $au) as $m) {
                $minutes++;
                if (! isset($noPers[$m->noPers])) {
                    $inconnus[$m->noPers] = true;
                }
            }

            return [
                'ok' => true,
                'erreur' => null,
                'duree_ms' => $this->ms($debut),
                'departements' => $departements,
                'departements_nouveaux' => $this->exemples($departementsNouveaux),
                'comptes' => $comptes,
                'comptes_nouveaux' => $this->exemples($comptesNouveaux),
                'comptes_a_qualifier' => CompteDsk::where('categorie', 'autre')->where('compte_effectif', false)->count() + count($comptesNouveaux),
                'salaries' => $salaries,
                'salaries_sans_email' => $this->exemples($sansEmail),
                'lignes_minutes' => $minutes,
                'minutes_salaries_inconnus' => $this->exemples(array_map('strval', array_keys($inconnus))),
                '_emails' => $emails,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'erreur' => $this->message($e), 'duree_ms' => $this->ms($debut), '_emails' => []];
        }
    }

    /** @return array<string, mixed> */
    private function verifierEpilogg(CarbonInterface $du, CarbonInterface $au): array
    {
        $debut = hrtime(true);

        try {
            $this->epilogg->verifierConnexion();

            $codesTarifs = Tarif::pluck('code')->all();
            $typesConnus = CategorieSuivi::query()->get(['epilogg_attend_type_id', 'tarif_id'])->keyBy('epilogg_attend_type_id');
            $types = 0;
            $typesNouveaux = 0;
            $sansTarif = [];
            $idsTypesSource = [];
            foreach ($this->epilogg->typesDeSuivi() as $t) {
                $types++;
                $idsTypesSource[$t->id] = true;
                $connu = $typesConnus->get($t->id);
                if ($connu === null) {
                    $typesNouveaux++;
                }
                // Un type déjà qualifié par l'Administrateur n'est jamais remis en cause ;
                // un type nouveau ou non qualifié sera classé AUTRE si son rate_name est inconnu.
                $tarifDeduit = Tarif::codeDepuisRateName($t->rateName);
                if (! $t->archive && ($connu === null || $connu->tarif_id === null) && ! in_array($tarifDeduit, $codesTarifs, true)) {
                    $sansTarif[] = $t->libelle.' (rate_name « '.($t->rateName ?? '∅').' »)';
                }
            }

            $actifs = 0;
            $actifsSansEmail = 0;
            $emails = [];
            $ids = [];
            foreach ($this->epilogg->salaries() as $s) {
                if (! $s->actif) {
                    continue;
                }
                $actifs++;
                $ids[$s->id] = true;
                $email = $s->emailNormalise();
                if ($email === null) {
                    $actifsSansEmail++;
                } else {
                    $emails[$email] = $s->nom ?? '';
                }
            }

            $prestations = 0;
            $utilisateurInconnu = 0;
            $typeInconnu = 0;
            $typesVus = [];
            foreach ($this->epilogg->prestations($du, $au) as $p) {
                $prestations++;
                $typesVus[$p->typeSuiviId] = true;
                if (! isset($ids[$p->utilisateurId])) {
                    $utilisateurInconnu++;
                }
            }
            // Un type référencé par une prestation mais absent de attend_types serait une
            // anomalie de la source : la prestation serait ignorée à l'import.
            foreach (array_keys($typesVus) as $id) {
                if (! isset($idsTypesSource[$id])) {
                    $typeInconnu++;
                }
            }

            return [
                'ok' => true,
                'erreur' => null,
                'duree_ms' => $this->ms($debut),
                'types' => $types,
                'types_nouveaux' => $typesNouveaux,
                'types_sans_tarif' => $this->exemples($sansTarif),
                'utilisateurs_actifs' => $actifs,
                'utilisateurs_actifs_sans_email' => $actifsSansEmail,
                'prestations' => $prestations,
                'prestations_utilisateur_inactif' => $utilisateurInconnu,
                'types_absents_du_referentiel' => $typeInconnu,
                '_emails' => $emails,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'erreur' => $this->message($e), 'duree_ms' => $this->ms($debut), '_emails' => []];
        }
    }

    /**
     * Appariement par email, tel que le fera l'import : les comptes EpiLogg actifs sont
     * recherchés parmi les salariés DSK de la période ET les utilisateurs déjà en base.
     *
     * @param  array<string, string>  $emailsDsk  email => nom
     * @param  array<string, string>  $emailsEpilogg  email => nom
     * @return array<string, mixed>
     */
    private function apparier(array $emailsDsk, array $emailsEpilogg): array
    {
        $connus = $emailsDsk + User::pluck('name', 'email')->mapWithKeys(fn ($n, $e) => [mb_strtolower((string) $e) => (string) $n])->all();

        $apparies = 0;
        $epiloggSansDsk = [];
        foreach ($emailsEpilogg as $email => $nom) {
            if (isset($connus[$email])) {
                $apparies++;
            } else {
                $epiloggSansDsk[] = $email;
            }
        }

        $dskSansEpilogg = [];
        foreach ($emailsDsk as $email => $nom) {
            if (! isset($emailsEpilogg[$email])) {
                $dskSansEpilogg[] = $email;
            }
        }

        return [
            'apparies' => $apparies,
            'epilogg_actifs_sans_dsk' => $this->exemples($epiloggSansDsk),
            'epilogg_actifs_sans_dsk_total' => count($epiloggSansDsk),
            'dsk_sans_epilogg' => $this->exemples($dskSansEpilogg),
            'dsk_sans_epilogg_total' => count($dskSansEpilogg),
        ];
    }

    /**
     * @param  array<int, string>  $liste
     * @return array<int, string>
     */
    private function exemples(array $liste): array
    {
        return array_slice(array_values($liste), 0, self::MAX_EXEMPLES);
    }

    private function ms(int|float $debut): int
    {
        return (int) round((hrtime(true) - $debut) / 1_000_000);
    }

    /** Message d'erreur sans secret : les mots de passe n'apparaissent jamais dans une exception PDO/HTTP de Laravel, mais on reste court. */
    private function message(Throwable $e): string
    {
        return mb_strimwidth(get_class($e).' : '.$e->getMessage(), 0, 500, '…');
    }
}

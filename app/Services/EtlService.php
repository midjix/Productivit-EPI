<?php

namespace App\Services;

use App\Enums\CategorieHeure;
use App\Enums\SourceImport;
use App\Enums\StatutImport;
use App\Etl\Contracts\ConnecteurDsk;
use App\Etl\Contracts\ConnecteurEpilogg;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\Import;
use App\Models\Service;
use App\Models\Tarif;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrateur ETL : Extract (connecteurs) → Transform (DTO → modèles) → Load (flux bruts),
 * puis consolidation du cache mensuel. Ne connaît les sources que par leurs interfaces.
 *
 * Stratégie « fenêtre remplacée » : pour la période importée, les flux bruts sont
 * supprimés puis réécrits dans une transaction. C'est idempotent (ré-importer = même
 * résultat) et c'est la seule façon de refléter une SUPPRESSION côté source — EpiLogg
 * efface physiquement une prestation annulée, DSK recalcule ses compteurs chaque jour.
 *
 * Chaque exécution est journalisée dans `imports` (statut, lignes, avertissements).
 */
class EtlService
{
    private const LOT = 500;

    public function __construct(
        private readonly ConnecteurDsk $dsk,
        private readonly ConnecteurEpilogg $epilogg,
        private readonly ConsolidationService $consolidation,
    ) {}

    /** Import quotidien planifié : fenêtre glissante (config epi.import_fenetre_jours) puis recalcul. */
    public function importerQuotidien(?User $declencheur = null): array
    {
        $au = CarbonImmutable::today();
        $du = $au->subDays((int) config('epi.import_fenetre_jours', 60));

        return $this->importerTout($du, $au, $declencheur);
    }

    /** @return array{dsk: Import, epilogg: Import, statistiques: int} */
    public function importerTout(CarbonInterface $du, CarbonInterface $au, ?User $declencheur = null): array
    {
        $dsk = $this->importerDsk($du, $au, $declencheur);
        $epilogg = $this->importerEpilogg($du, $au, $declencheur);
        $stats = $this->consolidation->recalculerPeriode($du, $au);

        return ['dsk' => $dsk, 'epilogg' => $epilogg, 'statistiques' => $stats];
    }

    // =========================================================================
    //  DSK
    // =========================================================================

    public function importerDsk(CarbonInterface $du, CarbonInterface $au, ?User $declencheur = null): Import
    {
        return $this->executer(SourceImport::Dsk, $du, $au, $declencheur, function (array &$resume) use ($du, $au) {
            $this->dsk->verifierConnexion();

            $services = $this->synchroniserServices();
            $comptes = $this->synchroniserComptesDsk();
            $resume['services'] = $services->count();
            $resume['comptes'] = $comptes->count();

            $utilisateurs = $this->synchroniserSalariesDsk($du, $au, $services, $resume);
            $resume['salaries'] = $utilisateurs->count();

            return $this->chargerMinutesDsk($du, $au, $utilisateurs, $comptes, $resume);
        });
    }

    /** @return \Illuminate\Support\Collection<string, int> codeDept => service_id */
    private function synchroniserServices()
    {
        foreach ($this->dsk->departements() as $d) {
            Service::updateOrCreate(['code' => $d->codeDept], ['nom' => $d->libelle, 'actif' => $d->actif]);
        }

        return Service::pluck('id', 'code');
    }

    /**
     * Nouveaux compteurs → catégorie « autre », hors total, en attente de qualification.
     * La classification faite par l'Administrateur n'est JAMAIS écrasée par l'import.
     *
     * @return \Illuminate\Support\Collection<int, int> noAccount => compte_dsk_id
     */
    private function synchroniserComptesDsk()
    {
        foreach ($this->dsk->comptes() as $c) {
            $compte = CompteDsk::firstOrNew(['no_account' => $c->noAccount]);
            $compte->libelle = $c->libelle;
            $compte->type_dsk = $c->type;

            if (! $compte->exists) {
                $compte->categorie = CategorieHeure::Autre;
                $compte->compte_effectif = false;
                $compte->actif = true;
            }

            $compte->save();
        }

        return CompteDsk::pluck('id', 'no_account');
    }

    /**
     * Salariés DSK → `users` (clé : dsk_no_pers). Le rôle et le mot de passe d'un compte
     * existant ne sont jamais touchés ; un nouveau salarié reçoit un mot de passe aléatoire
     * (il ne peut pas se connecter tant que l'Administrateur n'en a pas défini un).
     *
     * @return \Illuminate\Support\Collection<int, int> noPers => user_id
     */
    private function synchroniserSalariesDsk(CarbonInterface $du, CarbonInterface $au, $services, array &$resume)
    {
        $sansEmail = 0;

        foreach ($this->dsk->salaries($du, $au) as $s) {
            $user = User::firstOrNew(['dsk_no_pers' => $s->noPers]);
            $email = $s->emailNormalise();

            if ($email === null) {
                $sansEmail++;
                // Adresse technique : évite une collision et signale un appariement impossible.
                $email = $user->email ?? sprintf('dsk-%d@sans-email.invalid', $s->noPers);
            }

            $user->fill([
                'name' => $s->nomComplet,
                'email' => $email,
                'matricule' => $s->codePers,
                'service_id' => $s->codeDept !== null ? $services->get($s->codeDept) : $user->service_id,
                'date_entree' => $s->dateEntree,
                'date_sortie' => $s->dateSortie,
                'actif' => $s->dateSortie === null || $s->dateSortie->isFuture(),
            ]);

            if (! $user->exists) {
                $user->password = Str::password(32);
            }

            $user->save();
        }

        $resume['salaries_sans_email'] = $sansEmail;

        return User::whereNotNull('dsk_no_pers')->pluck('id', 'dsk_no_pers');
    }

    private function chargerMinutesDsk(CarbonInterface $du, CarbonInterface $au, $utilisateurs, $comptes, array &$resume): int
    {
        $lignes = [];
        $inconnus = [];
        $importId = $resume['import_id'];
        $horodatage = now();

        foreach ($this->dsk->minutesParJour($du, $au) as $m) {
            $userId = $utilisateurs->get($m->noPers);

            if ($userId === null) {
                $inconnus[$m->noPers] = true;

                continue;
            }

            $compteId = $comptes->get($m->noAccount);

            if ($compteId === null) {
                // Compteur apparu entre /accounts et /accounts/calc/all : créé « autre », à qualifier.
                $compteId = CompteDsk::create(['no_account' => $m->noAccount, 'libelle' => 'Compte '.$m->noAccount])->id;
                $comptes->put($m->noAccount, $compteId);
            }

            $lignes[] = [
                'user_id' => $userId, 'compte_dsk_id' => $compteId, 'date' => $m->date->toDateString(),
                'minutes' => $m->minutes, 'import_id' => $importId, 'created_at' => $horodatage, 'updated_at' => $horodatage,
            ];
        }

        $resume['salaries_dsk_inconnus'] = array_keys($inconnus);

        return $this->remplacerFenetre(DonneeDsk::class, $du, $au, $lignes);
    }

    // =========================================================================
    //  EpiLogg
    // =========================================================================

    public function importerEpilogg(CarbonInterface $du, CarbonInterface $au, ?User $declencheur = null): Import
    {
        return $this->executer(SourceImport::Epilogg, $du, $au, $declencheur, function (array &$resume) use ($du, $au) {
            $this->epilogg->verifierConnexion();

            $types = $this->synchroniserTypesSuivi($resume);
            $utilisateurs = $this->apparierSalariesEpilogg($resume);

            return $this->chargerPrestations($du, $au, $utilisateurs, $types, $resume);
        });
    }

    /**
     * Types de suivi → `categories_suivi` (clé : id EpiLogg). Le tarif est déduit du rate_name
     * à la CRÉATION seulement ; un tarif choisi par l'Administrateur n'est jamais écrasé.
     *
     * @return \Illuminate\Support\Collection<int, int> attend_type_id => categorie_suivi_id
     */
    private function synchroniserTypesSuivi(array &$resume)
    {
        $tarifs = Tarif::pluck('id', 'code');
        $nonQualifies = [];

        foreach ($this->epilogg->typesDeSuivi() as $t) {
            $categorie = CategorieSuivi::firstOrNew(['epilogg_attend_type_id' => $t->id]);
            $categorie->libelle = $t->libelle;
            $categorie->actif = ! $t->archive;

            if ($categorie->tarif_id === null) {
                $categorie->tarif_id = $tarifs->get(Tarif::codeDepuisRateName($t->rateName));
            }

            $categorie->save();

            if ($categorie->tarif_id === null && ! $t->archive) {
                $nonQualifies[] = $t->libelle;
            }
        }

        $resume['types_suivi'] = CategorieSuivi::count();
        $resume['types_non_qualifies'] = $nonQualifies;

        return CategorieSuivi::pluck('id', 'epilogg_attend_type_id');
    }

    /**
     * Appariement par email (recommandation CBC) : un utilisateur EpiLogg est relié au salarié
     * DSK dont l'email correspond. L'appariement est persisté (`epilogg_user_id`) ; les comptes
     * EpiLogg actifs sans correspondance sont remontés à l'Administrateur.
     *
     * @return \Illuminate\Support\Collection<int, int> epilogg user id => user_id
     */
    private function apparierSalariesEpilogg(array &$resume)
    {
        $parEmail = User::query()->get(['id', 'email', 'epilogg_user_id'])->keyBy(fn (User $u) => mb_strtolower($u->email));
        $sansCorrespondance = [];

        foreach ($this->epilogg->salaries() as $s) {
            $email = $s->emailNormalise();
            $user = $email !== null ? $parEmail->get($email) : null;

            if ($user === null) {
                if ($s->actif) {
                    $sansCorrespondance[] = $email ?? "id {$s->id}";
                }

                continue;
            }

            if ($user->epilogg_user_id !== $s->id) {
                User::whereKey($user->id)->update(['epilogg_user_id' => $s->id]);
            }
        }

        $resume['epilogg_sans_correspondance'] = $sansCorrespondance;

        return User::whereNotNull('epilogg_user_id')->pluck('id', 'epilogg_user_id');
    }

    private function chargerPrestations(CarbonInterface $du, CarbonInterface $au, $utilisateurs, $types, array &$resume): int
    {
        $lignes = [];
        $ignorees = 0;
        $importId = $resume['import_id'];
        $horodatage = now();

        foreach ($this->epilogg->prestations($du, $au) as $p) {
            $userId = $utilisateurs->get($p->utilisateurId);
            $categorieId = $types->get($p->typeSuiviId);

            if ($userId === null || $categorieId === null) {
                $ignorees++;

                continue;
            }

            $lignes[] = [
                'user_id' => $userId,
                'categorie_suivi_id' => $categorieId,
                'type_prestation' => $p->typePrestation !== null ? Str::limit($p->typePrestation, 147) : null,
                'date' => $p->date->toDateString(),
                'quantite' => $p->quantite,
                'forfait_deplacement' => $p->forfaitDeplacement,
                'minutes_service_source' => $p->minutesService,
                'minutes_deplacement_source' => $p->minutesDeplacement,
                'reference_externe' => (string) $p->id,
                'import_id' => $importId,
                'created_at' => $horodatage,
                'updated_at' => $horodatage,
            ];
        }

        $resume['prestations_ignorees'] = $ignorees;

        return $this->remplacerFenetre(DonneeEpilogg::class, $du, $au, $lignes);
    }

    // =========================================================================
    //  Commun
    // =========================================================================

    /**
     * Journalise l'exécution dans `imports` et garantit un statut final (succès / échec).
     *
     * @param  callable(array<string, mixed>&): int  $traitement  renvoie le nombre de lignes chargées
     */
    private function executer(SourceImport $source, CarbonInterface $du, CarbonInterface $au, ?User $declencheur, callable $traitement): Import
    {
        $import = Import::create([
            'source' => $source,
            'statut' => StatutImport::EnCours,
            'periode_debut' => $du->toDateString(),
            'periode_fin' => $au->toDateString(),
            'user_id' => $declencheur?->id,
            'demarre_le' => now(),
        ]);

        $resume = ['import_id' => $import->id];

        try {
            $nb = $traitement($resume);
            unset($resume['import_id']);

            $import->update([
                'statut' => StatutImport::Succes,
                'nb_lignes' => $nb,
                'message' => json_encode($resume, JSON_UNESCAPED_UNICODE),
                'termine_le' => now(),
            ]);
        } catch (Throwable $e) {
            $import->update([
                'statut' => StatutImport::Echec,
                'message' => Str::limit(get_class($e).' : '.$e->getMessage(), 60000),
                'termine_le' => now(),
            ]);

            throw $e;
        }

        return $import->refresh();
    }

    /**
     * Remplace les lignes de la fenêtre [du, au] par celles reçues, dans une transaction.
     *
     * @param  class-string<DonneeDsk|DonneeEpilogg>  $modele
     * @param  array<int, array<string, mixed>>  $lignes
     */
    private function remplacerFenetre(string $modele, CarbonInterface $du, CarbonInterface $au, array $lignes): int
    {
        return DB::transaction(function () use ($modele, $du, $au, $lignes) {
            $modele::query()->whereBetween('date', [$du->toDateString(), $au->toDateString()])->delete();

            foreach (array_chunk($lignes, self::LOT) as $lot) {
                $modele::query()->insert($lot);
            }

            return count($lignes);
        });
    }
}

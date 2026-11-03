<?php

namespace App\Console\Commands;

use App\Services\DiagnosticSourcesService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Répétition générale avant un import réel : vérifie les sources SANS RIEN ÉCRIRE.
 *
 *   php artisan epi:verifier                          fenêtre glissante (config epi.import_fenetre_jours)
 *   php artisan epi:verifier --source=dsk             une seule source
 *   php artisan epi:verifier --from=2025-01-01 --to=2025-12-31
 *   php artisan epi:verifier --json                   sortie JSON (supervision, test d'installation par CBC)
 *
 * Code de retour : 0 si toutes les sources demandées répondent, 1 sinon — utilisable
 * dans un script d'installation comme test de bon fonctionnement.
 */
class VerifierSources extends Command
{
    protected $signature = 'epi:verifier
        {--source=all : dsk | epilogg | all}
        {--from= : début de période (AAAA-MM-JJ), défaut : aujourd\'hui − fenêtre}
        {--to= : fin de période (AAAA-MM-JJ), défaut : aujourd\'hui}
        {--json : sortie JSON lisible par un programme}';

    protected $description = 'Vérifie les sources DSK / EpiLogg en lecture seule : connexion, volumes, appariement par email, référentiels à qualifier (aucune écriture)';

    public function handle(DiagnosticSourcesService $diagnostic): int
    {
        $source = (string) $this->option('source');

        if (! in_array($source, ['all', 'dsk', 'epilogg'], true)) {
            $this->components->error("Source inconnue « {$source} » : dsk, epilogg ou all.");

            return self::INVALID;
        }

        $au = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : CarbonImmutable::today();
        $du = $this->option('from')
            ? CarbonImmutable::parse($this->option('from'))
            : $au->subDays((int) config('epi.import_fenetre_jours', 60));

        $resultat = $diagnostic->verifier($du, $au, $source);

        if ($this->option('json')) {
            $this->line(json_encode($resultat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $resultat['pret'] ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info(sprintf(
            'Diagnostic des sources du %s au %s — lecture seule, aucune écriture (ni dans les sources, ni en base locale).',
            $du->toDateString(), $au->toDateString(),
        ));

        if ($resultat['dsk'] !== null) {
            $this->afficherDsk($resultat['dsk']);
        }

        if ($resultat['epilogg'] !== null) {
            $this->afficherEpilogg($resultat['epilogg']);
        }

        if ($resultat['appariement'] !== null) {
            $this->afficherAppariement($resultat['appariement']);
        }

        $this->newLine();

        if ($resultat['pret']) {
            $this->components->info('Verdict : prêt pour l\'import. Après l\'import, qualifier dans l\'administration les compteurs DSK et les types de suivi signalés ci-dessus, puis recalculer.');

            return self::SUCCESS;
        }

        $this->components->error('Verdict : au moins une source ne répond pas, l\'import échouerait. Vérifier le VPN, les identifiants (.env) et l\'ouverture réseau.');

        return self::FAILURE;
    }

    /** @param  array<string, mixed>  $d */
    private function afficherDsk(array $d): void
    {
        $this->titre('DSK (pointage RH, API REST)', $d);

        if (! $d['ok']) {
            return;
        }

        $this->detail('Départements', $d['departements'], $this->annexe($d['departements_nouveaux'], 'nouveau(x), à rapprocher de l\'organigramme'));
        $this->detail('Compteurs (comptes d\'heures)', $d['comptes'], $this->annexe($d['comptes_nouveaux'], 'nouveau(x)'));
        $this->detail('Compteurs à qualifier après import', $d['comptes_a_qualifier']);
        $this->detail('Salariés sur la période', $d['salaries'], $this->annexe($d['salaries_sans_email'], 'sans email : appariement EpiLogg impossible'));
        $this->detail('Lignes de minutes (salarié × jour × compteur)', $d['lignes_minutes'], $this->annexe($d['minutes_salaries_inconnus'], 'noPers absent(s) de la liste des salariés : lignes ignorées'));

        $this->listes([
            'Départements nouveaux' => $d['departements_nouveaux'],
            'Compteurs nouveaux (classés « autre, hors total » à l\'import)' => $d['comptes_nouveaux'],
            'Salariés sans email' => $d['salaries_sans_email'],
        ]);
    }

    /** @param  array<string, mixed>  $e */
    private function afficherEpilogg(array $e): void
    {
        $this->titre('EpiLogg (prestations, base SQL)', $e);

        if (! $e['ok']) {
            return;
        }

        $this->detail('Types de suivi', $e['types'], $e['types_nouveaux'] > 0 ? "{$e['types_nouveaux']} nouveau(x)" : null);
        $this->detail('Types actifs sans tarif reconnu (→ activité interne)', count($e['types_sans_tarif']));
        $this->detail('Comptes utilisateurs actifs', $e['utilisateurs_actifs'], $e['utilisateurs_actifs_sans_email'] > 0 ? "{$e['utilisateurs_actifs_sans_email']} sans email" : null);
        $this->detail('Prestations sur la période', $e['prestations'], $e['prestations_utilisateur_inactif'] > 0 ? "{$e['prestations_utilisateur_inactif']} d'un compte inactif ou supprimé" : null);

        if ($e['types_absents_du_referentiel'] > 0) {
            $this->components->warn("{$e['types_absents_du_referentiel']} type(s) référencé(s) par des prestations mais absent(s) de attend_types : ces prestations seraient ignorées.");
        }

        $this->listes(['Types à qualifier par l\'Administrateur (tarif non déduit du rate_name)' => $e['types_sans_tarif']]);
    }

    /** @param  array<string, mixed>  $a */
    private function afficherAppariement(array $a): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow;options=bold>Appariement DSK ↔ EpiLogg par email</>', '');
        $this->detail('Comptes EpiLogg actifs appariés', $a['apparies']);
        $this->detail('Comptes EpiLogg actifs sans salarié DSK (prestations ignorées)', $a['epilogg_actifs_sans_dsk_total']);
        $this->detail('Salariés DSK sans compte EpiLogg (0 prestation)', $a['dsk_sans_epilogg_total']);

        $this->listes([
            'EpiLogg sans correspondance DSK' => $a['epilogg_actifs_sans_dsk'],
            'DSK sans compte EpiLogg' => $a['dsk_sans_epilogg'],
        ]);
    }

    /** @param  array<string, mixed>  $section */
    private function titre(string $libelle, array $section): void
    {
        $this->newLine();
        $etat = $section['ok']
            ? '<fg=green>joignable</> ('.$section['duree_ms'].' ms)'
            : '<fg=red>INJOIGNABLE</>';
        $this->components->twoColumnDetail("<options=bold>{$libelle}</>", $etat);

        if (! $section['ok']) {
            $this->components->error($section['erreur']);
        }
    }

    private function detail(string $libelle, int|string $valeur, ?string $annexe = null): void
    {
        $this->components->twoColumnDetail($libelle, (string) $valeur.($annexe !== null ? "  <fg=yellow>{$annexe}</>" : ''));
    }

    /** @param  array<int, string>  $liste */
    private function annexe(array $liste, string $texte): ?string
    {
        return $liste === [] ? null : count($liste).' '.$texte;
    }

    /** @param  array<string, array<int, string>>  $listes */
    private function listes(array $listes): void
    {
        foreach ($listes as $titre => $elements) {
            if ($elements === []) {
                continue;
            }

            $this->line("  <fg=yellow>{$titre}</>".(count($elements) >= DiagnosticSourcesService::MAX_EXEMPLES ? ' (premiers '.DiagnosticSourcesService::MAX_EXEMPLES.')' : '').' :');
            $this->components->bulletList($elements);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\CategorieHeure;
use App\Enums\Role;
use App\Enums\SourceImport;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\Import;
use App\Models\Service;
use App\Models\Tarif;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Jeu de données de DÉMONSTRATION — environnements local/testing uniquement.
 *
 * Simule ce que les imports DSK / EpiLogg produiront : types de suivi, compteurs
 * DSK qualifiés, salariés FICTIFS (RGPD : jamais les vrais noms de la Fondation)
 * et trois mois de flux bruts réalistes. Le cache `statistiques_mensuelles` n'est
 * PAS alimenté ici : c'est le rôle exclusif de l'EtlService (une seule source de calcul).
 *
 * Comptes de démonstration (mot de passe : `password`) :
 *   admin@epi.test                → Administrateur
 *   coord.<service>@epi.test      → Coordinateur du service
 *   <prenom>.<nom>@epi.test       → Salariés
 *
 * Relancer le seeder repart d'une base vide (les données de démo sont jetables).
 */
class DemoSeeder extends Seeder
{
    /** Types de suivi EpiLogg (« Données app EPI.xlsx ») : libellé => [code tarif, code service]. */
    private const TYPES_SUIVI = [
        '8.1 AISP' => ['8.1', 'AISP'],
        '8.1 AISP (Inter-C)' => ['8.1', 'AISP'],
        '8.1 Follow Up' => ['8.1', 'FOLLOWUP'],
        '8.1 Thérapie familiale' => ['8.1', 'FOLLOWUP'],
        '8.2 SLEMO' => ['8.2', 'LOGEMENT'],
        '9.1 Service Psychologique' => ['9.1', 'PSY'],
        'Comité des Fêtes' => [Tarif::CODE_AUTRE, null],
        'Comité du Film' => [Tarif::CODE_AUTRE, null],
        'Connect & Grow' => [Tarif::CODE_AUTRE, null],
        "Coordination d'équipe" => [Tarif::CODE_AUTRE, null],
        'Forest4Youth' => [Tarif::CODE_AUTRE, null],
        'Permis de conduire' => [Tarif::CODE_AUTRE, null],
    ];

    /** Compteurs DSK (types d'heures de la Fondation) : noAccount fictif => [libellé, catégorie]. */
    private const COMPTES_DSK = [
        10 => ['Heures normales', CategorieHeure::Normal],
        20 => ['Congés annuels', CategorieHeure::Conge],
        21 => ['Congés exceptionnels', CategorieHeure::Conge],
        22 => ['Récupération de solde', CategorieHeure::Conge],
        30 => ['Maladie', CategorieHeure::Maladie],
        40 => ['Formation standard', CategorieHeure::Formation],
        41 => ['Congé formation ministère', CategorieHeure::Formation],
        42 => ['Congé formation EPI', CategorieHeure::Formation],
        50 => ['Supervision individuelle', CategorieHeure::Supervision],
        51 => ['Supervision groupe', CategorieHeure::Supervision],
        90 => ['Compteur technique DSK (hors total)', CategorieHeure::Autre],
    ];

    private const SALARIES_PAR_SERVICE = ['FOLLOWUP' => 4, 'AISP' => 4, 'PSY' => 2, 'LOGEMENT' => 1];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoSeeder ignoré : réservé aux environnements local/testing.');

            return;
        }

        $this->purger();

        $this->seedTypesSuivi();
        $this->seedComptesDsk();
        $utilisateurs = $this->seedUtilisateurs();
        $this->seedFlux($utilisateurs);
    }

    private function purger(): void
    {
        DB::table('statistiques_mensuelles')->delete();
        DB::table('donnees_epilogg')->delete();
        DB::table('donnees_dsk')->delete();
        DB::table('imports')->delete();
        DB::table('users')->delete();
        DB::table('categories_suivi')->delete();
        DB::table('comptes_dsk')->delete();
    }

    private function seedTypesSuivi(): void
    {
        $tarifs = Tarif::pluck('id', 'code');
        $services = Service::pluck('id', 'code');
        $id = 100;

        foreach (self::TYPES_SUIVI as $libelle => [$codeTarif, $codeService]) {
            CategorieSuivi::create([
                'libelle' => $libelle,
                'tarif_id' => $tarifs[$codeTarif],
                'service_id' => $codeService ? $services[$codeService] : null,
                'epilogg_attend_type_id' => $id++,
                'actif' => true,
            ]);
        }

        // Cas réel : un type apparu dans EpiLogg, pas encore qualifié par l'Administrateur.
        CategorieSuivi::create(['libelle' => 'Atelier parentalité (nouveau)', 'epilogg_attend_type_id' => $id, 'actif' => true]);
    }

    private function seedComptesDsk(): void
    {
        foreach (self::COMPTES_DSK as $noAccount => [$libelle, $categorie]) {
            CompteDsk::create([
                'no_account' => $noAccount,
                'libelle' => $libelle,
                'categorie' => $categorie,
                'compte_effectif' => $categorie !== CategorieHeure::Autre,
                'actif' => true,
            ]);
        }
    }

    /** @return array<int, User> salariés (hors admin) */
    private function seedUtilisateurs(): array
    {
        $services = Service::all()->keyBy('code');

        User::factory()->administrateur()->create([
            'name' => 'Admin EPI',
            'email' => 'admin@epi.test',
            'service_id' => $services['ADMIN']->id,
        ]);

        $salaries = [];

        foreach (self::SALARIES_PAR_SERVICE as $code => $nb) {
            $service = $services[$code];

            $salaries[] = User::factory()->coordinateur()->create([
                'email' => 'coord.'.strtolower($code).'@epi.test',
                'service_id' => $service->id,
            ]);

            foreach (User::factory()->count($nb)->create(['service_id' => $service->id]) as $u) {
                $salaries[] = $u;
            }
        }

        // Cas réels à exercer : un salarié parti, un salarié sans correspondance EpiLogg.
        $salaries[] = User::factory()->sorti()->create(['service_id' => $services['AISP']->id]);
        $salaries[] = User::factory()->sansEpilogg()->create(['service_id' => $services['FOLLOWUP']->id]);

        return $salaries;
    }

    /** @param  array<int, User>  $salaries */
    private function seedFlux(array $salaries): void
    {
        $fin = CarbonImmutable::today()->endOfMonth();
        $debut = $fin->subMonths(3)->startOfMonth();

        $importDsk = Import::factory()->create(['source' => SourceImport::Dsk, 'periode_debut' => $debut, 'periode_fin' => $fin]);
        $importEpilogg = Import::factory()->create(['source' => SourceImport::Epilogg, 'periode_debut' => $debut, 'periode_fin' => $fin]);

        $comptes = CompteDsk::all()->keyBy('no_account');
        $typesParService = CategorieSuivi::whereNotNull('tarif_id')->get()->groupBy('service_id');
        $typesInternes = CategorieSuivi::whereNotNull('tarif_id')->whereNull('service_id')->get();

        $dsk = [];
        $epilogg = [];
        $ref = 500000;

        foreach ($salaries as $salarie) {
            $typesMetier = $typesParService->get($salarie->service_id, collect());

            foreach (CarbonPeriod::create($debut, $fin) as $jour) {
                if ($jour->isWeekend() || ($salarie->date_sortie && $jour->gt($salarie->date_sortie))) {
                    continue;
                }

                $jourTxt = $jour->toDateString();
                $base = ['user_id' => $salarie->id, 'date' => $jourTxt, 'import_id' => $importDsk->id, 'created_at' => now(), 'updated_at' => now()];

                // Journée DSK : 8 h réparties selon un tirage réaliste.
                $tirage = random_int(1, 100);
                $noAccount = match (true) {
                    $tirage <= 78 => 10,   // heures normales
                    $tirage <= 86 => 20,   // congés annuels
                    $tirage <= 88 => 22,   // récupération
                    $tirage <= 92 => 30,   // maladie
                    $tirage <= 96 => 40,   // formation
                    default => 50,         // supervision
                };
                $dsk[] = $base + ['compte_dsk_id' => $comptes[$noAccount]->id, 'minutes' => 480];

                // Prestations EpiLogg uniquement les jours travaillés et si le salarié est apparié.
                if ($noAccount !== 10 || $salarie->epilogg_user_id === null) {
                    continue;
                }

                // 4 à 8 prestations d'environ 1 forfait (≈ 1 h) par journée de 8 h : efficacité
                // moyenne proche de l'objectif 0,75, avec des salariés au-dessus et en dessous.
                for ($i = random_int(4, 8); $i > 0; $i--) {
                    $interne = $typesInternes->isNotEmpty() && random_int(1, 10) === 1;
                    $type = $interne ? $typesInternes->random() : ($typesMetier->isNotEmpty() ? $typesMetier->random() : null);

                    if ($type === null) {
                        continue;
                    }

                    $epilogg[] = [
                        'user_id' => $salarie->id,
                        'categorie_suivi_id' => $type->id,
                        'type_prestation' => $interne ? 'Planification administrative' : 'Entretien / réunion avec bénéficiaire ou entourage immédiat',
                        'date' => $jourTxt,
                        'quantite' => [0.5, 1, 1, 1, 1.5, 2][random_int(0, 5)],
                        'reference_externe' => (string) $ref++,
                        'import_id' => $importEpilogg->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($dsk, 500) as $chunk) {
            DonneeDsk::insert($chunk);
        }
        foreach (array_chunk($epilogg, 500) as $chunk) {
            DonneeEpilogg::insert($chunk);
        }

        $importDsk->update(['nb_lignes' => count($dsk)]);
        $importEpilogg->update(['nb_lignes' => count($epilogg)]);
    }
}

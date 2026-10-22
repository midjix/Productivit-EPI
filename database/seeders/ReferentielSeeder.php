<?php

namespace Database\Seeders;

use App\Models\RegleConversion;
use App\Models\Service;
use App\Models\Tarif;
use Illuminate\Database\Seeder;

/**
 * Référentiel métier MINIMAL, valable en production comme en développement.
 *
 * Contient uniquement ce que les sources externes ne fournissent pas :
 *  - les tarifs (8.1, 8.2, 9.1, AUTRE) et leur barème initial ;
 *  - l'organigramme de départ (les codes seront alignés sur `codeDept` DSK au premier import).
 *
 * Les types de suivi, les compteurs DSK et les salariés viennent des IMPORTS
 * (EpiLogg / DSK), jamais d'un seeder : on ne duplique pas une source de vérité.
 *
 * Idempotent : `updateOrCreate` sur la clé naturelle. Les règles de conversion ne sont
 * créées que si le tarif n'en a aucune (on ne réécrit jamais un historique existant).
 */
class ReferentielSeeder extends Seeder
{
    /** Barème validé avec la Fondation (« Données app EPI.xlsx », 2026). */
    private const TARIFS = [
        ['code' => '8.1', 'libelle' => 'Aide sociale, accompagnement et suivi (AISP, Follow Up, thérapie familiale)', 'facturable' => true, 'minutes' => 60],
        ['code' => '8.2', 'libelle' => 'Service logement (SLEMO)', 'facturable' => true, 'minutes' => 60],
        ['code' => '9.1', 'libelle' => 'Service psychologique', 'facturable' => true, 'minutes' => 30],
        ['code' => Tarif::CODE_AUTRE, 'libelle' => 'Activité interne (comités, coordination, projets)', 'facturable' => false, 'minutes' => 60],
    ];

    /** Organigramme (« Données app EPI.xlsx », feuille Organigramme). */
    private const SERVICES = [
        ['code' => 'FOLLOWUP', 'nom' => 'Follow Up'],
        ['code' => 'AISP', 'nom' => 'AISP'],
        ['code' => 'PSY', 'nom' => 'Service Psychologique'],
        ['code' => 'LOGEMENT', 'nom' => 'Service Logement'],
        ['code' => 'ADMIN', 'nom' => 'Administration'],
    ];

    /**
     * Date d'ouverture du barème initial : suffisamment ancienne pour couvrir
     * les 5 ans d'historique à recalculer. À ajuster si la Fondation fournit
     * des barèmes antérieurs différents (ils seront alors ajoutés comme règles closes).
     */
    private const DEBUT_BAREME_INITIAL = '2020-01-01';

    public function run(): void
    {
        foreach (self::TARIFS as $t) {
            $tarif = Tarif::updateOrCreate(
                ['code' => $t['code']],
                ['libelle' => $t['libelle'], 'facturable' => $t['facturable'], 'actif' => true],
            );

            if ($tarif->reglesConversion()->doesntExist()) {
                RegleConversion::create([
                    'tarif_id' => $tarif->id,
                    'minutes' => $t['minutes'],
                    'date_debut_validite' => self::DEBUT_BAREME_INITIAL,
                    'date_fin_validite' => null,
                    'commentaire' => 'Barème initial (Données app EPI.xlsx)',
                ]);
            }
        }

        foreach (self::SERVICES as $s) {
            Service::updateOrCreate(['code' => $s['code']], ['nom' => $s['nom'], 'actif' => true]);
        }
    }
}

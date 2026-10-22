<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructuration du modèle après validation des règles métier avec la Fondation
 * (fichier « Données app EPI.xlsx ») et des modes d'accès aux sources
 * (échanges CBC / Fondation des 26-28/08/2026).
 *
 * Ce que les règles métier ont changé :
 *  1. Le code 8.1 / 8.2 / 9.1 n'est PAS une catégorie de suivi : c'est un TARIF
 *     (nature du service aux yeux de l'État) partagé par plusieurs types de suivi
 *     (« 8.1 AISP », « 8.1 Follow Up », « 8.1 Thérapie familiale »…). La valeur en
 *     minutes d'un forfait dépend du tarif (8.1 = 60, 8.2 = 60, 9.1 = 30, autres = 60).
 *     => nouvelle entité `tarifs`, la matrice historisée (`regles_conversion`)
 *        est rattachée au tarif, et `categories_suivi` pointe vers son tarif.
 *  2. Un salarié saisit un NOMBRE DE FORFAITS, éventuellement décimal (1,5 forfait).
 *     => `donnees_epilogg.quantite` passe d'un entier à un décimal.
 *  3. Le ratio n'est pas « prestations / heures normales » mais une cascade :
 *        heures dispo EPI    = total DSK - congés - maladie
 *        heures dispo client = dispo EPI - formation - supervision - activité interne
 *        efficacité (réelle) = prestations facturables / heures dispo client
 *        efficacité brute    = (facturables + activité interne) / total DSK
 *     => la table de cache `statistiques_mensuelles` est recréée avec ces indicateurs.
 *
 * Ce que l'accès aux sources a changé (voir entrée 3 de PREPARATION-JURY.md) :
 *  - `users.epilogg_user_id` (apparié par email, aucun identifiant commun avec DSK) ;
 *  - `categories_suivi.epilogg_attend_type_id` (rapprochement sur l'id EpiLogg, stable,
 *    plutôt que sur `rate_name` qui est nullable et modifiable) ;
 *  - `donnees_epilogg.reference_externe` NOT NULL (= `benefits.id`, toujours présent).
 *
 * Pourquoi une migration unique et non des retouches des migrations du 16/08 :
 * celles-ci sont déjà appliquées ; on n'édite jamais une migration exécutée.
 * La table `statistiques_mensuelles` est un cache recalculable : la recréer ne perd
 * aucune donnée source. La matrice de conversion change de clé (catégorie -> tarif) :
 * ses lignes de test sont purgées et seront re-créées par le seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1. Tarifs (nature du service, porteur du barème) -------------------
        Schema::create('tarifs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('8.1 | 8.2 | 9.1 | AUTRE (rate_name EpiLogg normalisé)');
            $table->string('libelle', 100);
            $table->boolean('facturable')->default(true)
                ->comment('Vrai = prestation facturable (numérateur) ; faux = activité interne (déduite des heures dispo client)');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // ---- 2. Matrice de conversion : rattachée au tarif ---------------------
        DB::table('regles_conversion')->delete();

        // MariaDB adosse la clé étrangère à l'index composite existant (pas d'index
        // dédié) : la contrainte doit être supprimée AVANT l'index, puis la colonne.
        Schema::table('regles_conversion', function (Blueprint $table) {
            $table->dropForeign(['categorie_suivi_id']);
            $table->dropIndex('regles_conversion_validite_idx');
            $table->dropColumn('categorie_suivi_id');
        });

        Schema::table('regles_conversion', function (Blueprint $table) {
            $table->foreignId('tarif_id')->after('id')->constrained('tarifs')->restrictOnDelete();
            $table->index(['tarif_id', 'date_debut_validite', 'date_fin_validite'], 'regles_conversion_validite_idx');
        });

        // ---- 3. Types de suivi : libellé EpiLogg + tarif + id EpiLogg ---------
        Schema::table('categories_suivi', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::table('categories_suivi', function (Blueprint $table) {
            $table->foreignId('tarif_id')->nullable()->after('id')->constrained('tarifs')->restrictOnDelete()
                ->comment('Tarif applicable ; NULL tant que le type n\'est pas qualifié par l\'Administrateur');
            $table->unsignedBigInteger('epilogg_attend_type_id')->nullable()->unique()->after('tarif_id')
                ->comment('Identifiant EpiLogg du type de suivi (attend_types.id)');
        });

        // ---- 4. Prestations : quantité décimale, référence obligatoire --------
        Schema::table('donnees_epilogg', function (Blueprint $table) {
            $table->decimal('quantite', 8, 2)->default(1)
                ->comment('Nombre de forfaits saisis par le salarié (décimal possible : 1,5)')
                ->change();
            $table->string('reference_externe', 100)->nullable(false)
                ->comment('Identifiant de la prestation dans EpiLogg (benefits.id)')
                ->change();
            $table->string('type_prestation', 150)->nullable()->after('categorie_suivi_id')
                ->comment('Libellé EpiLogg du type de prestation (entretien, rapport, déplacement…) — filtre d\'affichage');
        });

        // ---- 5. Utilisateurs : pivot EpiLogg ----------------------------------
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('epilogg_user_id')->nullable()->unique()->after('dsk_no_pers')
                ->comment('Identifiant du salarié dans EpiLogg (users.id), apparié par email');
            $table->string('matricule', 30)->nullable()
                ->comment('Matricule RH DSK (codePers) — pivot DSK uniquement')
                ->change();
        });

        // ---- 6. Cache mensuel : indicateurs validés avec la Fondation ---------
        Schema::dropIfExists('statistiques_mensuelles');

        Schema::create('statistiques_mensuelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');

            // Heures DSK (minutes entières, par catégorie de compteur)
            $table->unsignedInteger('minutes_total')->default(0)->comment('« Total hrs dispo » : somme des comptes DSK effectifs');
            $table->unsignedInteger('minutes_normales')->default(0);
            $table->unsignedInteger('minutes_conge')->default(0)->comment('Congés annuels, exceptionnels, récupération de solde');
            $table->unsignedInteger('minutes_maladie')->default(0);
            $table->unsignedInteger('minutes_formation')->default(0)->comment('Formation standard, congés formation ministère / EPI');
            $table->unsignedInteger('minutes_supervision')->default(0)->comment('Supervision individuelle et de groupe');
            $table->unsignedInteger('minutes_deplacement')->default(0);
            $table->unsignedInteger('minutes_autres')->default(0);

            // Prestations EpiLogg (minutes décimales : quantité décimale × barème)
            $table->decimal('nb_prestations', 10, 2)->default(0)->comment('Somme des forfaits saisis');
            $table->decimal('minutes_prestations_facturables', 10, 2)->default(0)->comment('Forfaits des tarifs facturables × barème en vigueur à la date de chaque prestation');
            $table->decimal('minutes_activite_interne', 10, 2)->default(0)->comment('Forfaits des types non facturables (comités, coordination…) × barème');

            // Cascade de disponibilité (dénormalisée pour l\'API et l\'export)
            $table->integer('minutes_dispo_epi')->default(0)->comment('total - congés - maladie');
            $table->decimal('minutes_dispo_client', 10, 2)->default(0)->comment('dispo EPI - formation - supervision - activité interne');

            // Résultats
            $table->decimal('ratio', 8, 4)->nullable()->comment('Efficacité réelle = facturables / dispo client ; NULL si dispo client <= 0');
            $table->decimal('ratio_brut', 8, 4)->nullable()->comment('Efficacité brute = (facturables + activité interne) / total ; NULL si total = 0');
            $table->timestamp('calcule_le');
            $table->timestamps();

            $table->unique(['user_id', 'annee', 'mois'], 'stats_mensuelles_user_periode_unique');
            $table->index(['annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistiques_mensuelles');

        Schema::create('statistiques_mensuelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->unsignedInteger('minutes_effectives')->default(0);
            $table->unsignedInteger('minutes_normales')->default(0);
            $table->unsignedInteger('minutes_formation')->default(0);
            $table->unsignedInteger('minutes_conge')->default(0);
            $table->unsignedInteger('minutes_maladie')->default(0);
            $table->unsignedInteger('minutes_deplacement')->default(0);
            $table->unsignedInteger('nb_prestations')->default(0);
            $table->unsignedInteger('minutes_prestations')->default(0);
            $table->decimal('ratio', 8, 4)->nullable();
            $table->timestamp('calcule_le');
            $table->timestamps();
            $table->unique(['user_id', 'annee', 'mois'], 'stats_mensuelles_user_periode_unique');
            $table->index(['annee', 'mois']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule', 30)->nullable()
                ->comment('Matricule RH (codePers DSK) — pivot de jointure DSK/EpiLogg')
                ->change();
            $table->dropUnique(['epilogg_user_id']);
            $table->dropColumn('epilogg_user_id');
        });

        Schema::table('donnees_epilogg', function (Blueprint $table) {
            $table->dropColumn('type_prestation');
            $table->string('reference_externe', 100)->nullable()
                ->comment('Identifiant de la prestation dans EpiLogg')
                ->change();
            $table->unsignedSmallInteger('quantite')->default(1)->comment('Nombre de forfaits')->change();
        });

        Schema::table('categories_suivi', function (Blueprint $table) {
            $table->dropUnique(['epilogg_attend_type_id']);
            $table->dropColumn('epilogg_attend_type_id');
            $table->dropConstrainedForeignId('tarif_id');
        });

        Schema::table('categories_suivi', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->unique()->after('id')->comment('Code EpiLogg du forfait, ex. 8.1');
        });

        DB::table('regles_conversion')->delete();

        Schema::table('regles_conversion', function (Blueprint $table) {
            $table->dropForeign(['tarif_id']);
            $table->dropIndex('regles_conversion_validite_idx');
            $table->dropColumn('tarif_id');
        });

        Schema::table('regles_conversion', function (Blueprint $table) {
            $table->foreignId('categorie_suivi_id')->after('id')->constrained('categories_suivi')->restrictOnDelete();
            $table->index(['categorie_suivi_id', 'date_debut_validite', 'date_fin_validite'], 'regles_conversion_validite_idx');
        });

        Schema::dropIfExists('tarifs');
    }
};

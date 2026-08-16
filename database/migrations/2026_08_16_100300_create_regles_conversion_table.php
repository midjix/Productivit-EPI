<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matrice de conversion forfait → minutes, HISTORISÉE.
 *
 * Règle d'or : on ne modifie jamais une règle en place. Changer la valeur d'un
 * forfait = clôturer la règle courante (date_fin_validite = veille) + créer une
 * nouvelle règle (date_debut_validite = jour J), le tout dans une transaction.
 * Un recalcul portant sur une période passée retrouve ainsi le barème de l'époque.
 *
 * Invariant métier (garanti par le service + tests, non exprimable en contrainte SQL
 * simple à cause des NULL) : pour une catégorie donnée, les intervalles de validité
 * ne se chevauchent pas, et au plus une règle est ouverte (date_fin_validite NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regles_conversion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_suivi_id')->constrained('categories_suivi')->restrictOnDelete();
            $table->unsignedInteger('minutes')->comment('Durée forfaitaire d\'une prestation, en minutes');
            $table->date('date_debut_validite');
            $table->date('date_fin_validite')->nullable()->comment('NULL = règle en vigueur');
            $table->string('commentaire', 255)->nullable()->comment('Motif du changement de barème');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Administrateur à l\'origine de la règle (traçabilité)');
            $table->timestamps();

            // Recherche « règle en vigueur à la date D pour la catégorie C »
            $table->index(['categorie_suivi_id', 'date_debut_validite', 'date_fin_validite'], 'regles_conversion_validite_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regles_conversion');
    }
};

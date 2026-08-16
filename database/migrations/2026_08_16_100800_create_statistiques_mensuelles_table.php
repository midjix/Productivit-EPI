<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache des résultats consolidés : une ligne par (salarié, mois).
 *
 * L'API de consultation lit EXCLUSIVEMENT cette table — jamais les flux bruts —
 * pour rester performante sur 5 ans d'historique. Elle est (re)calculée par
 * l'EtlService après chaque import ou changement de barème.
 *
 * Toutes les durées sont en minutes (unité native de DSK et de la matrice de
 * conversion) ; la conversion en heures est un simple affichage côté front.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistiques_mensuelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');

            // Dénominateur et sa décomposition (issus de DSK)
            $table->unsignedInteger('minutes_effectives')->default(0)->comment('Somme des comptes marqués compte_effectif');
            $table->unsignedInteger('minutes_normales')->default(0);
            $table->unsignedInteger('minutes_formation')->default(0);
            $table->unsignedInteger('minutes_conge')->default(0);
            $table->unsignedInteger('minutes_maladie')->default(0);
            $table->unsignedInteger('minutes_deplacement')->default(0);

            // Numérateur (issu d'EpiLogg × matrice de conversion)
            $table->unsignedInteger('nb_prestations')->default(0);
            $table->unsignedInteger('minutes_prestations')->default(0)->comment('Forfaits convertis via le barème en vigueur à la date de chaque prestation');

            // Résultat
            $table->decimal('ratio', 8, 4)->nullable()->comment('minutes_prestations / minutes_effectives ; NULL si aucune heure effective');
            $table->timestamp('calcule_le');
            $table->timestamps();

            $table->unique(['user_id', 'annee', 'mois'], 'stats_mensuelles_user_periode_unique');
            $table->index(['annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistiques_mensuelles');
    }
};

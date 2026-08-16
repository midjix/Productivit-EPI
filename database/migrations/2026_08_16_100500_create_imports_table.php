<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des exécutions de l'ETL (une ligne par import DSK ou EpiLogg).
 * Sert à l'Administrateur pour superviser les imports (CRON quotidien ou manuel),
 * et à la traçabilité : chaque donnée brute référence l'import qui l'a produite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->comment('dsk | epilogg (App\Enums\SourceImport)');
            $table->string('statut', 20)->default('en_cours')->comment('en_cours | succes | echec (App\Enums\StatutImport)');
            $table->date('periode_debut')->comment('Début de la période importée');
            $table->date('periode_fin')->comment('Fin de la période importée');
            $table->unsignedInteger('nb_lignes')->default(0);
            $table->text('message')->nullable()->comment('Détail d\'erreur ou résumé');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Déclencheur humain ; NULL = planificateur (CRON)');
            $table->timestamp('demarre_le');
            $table->timestamp('termine_le')->nullable();
            $table->timestamps();

            $table->index(['source', 'demarre_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};

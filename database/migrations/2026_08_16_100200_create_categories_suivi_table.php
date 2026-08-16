<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Types d'interventions EpiLogg (forfaits) : 8.1 AISP, 9.1 Service Psychologique…
 * La valeur en minutes n'est PAS ici : elle vit dans `regles_conversion`
 * (historisée), une catégorie pouvant changer de barème au fil du temps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_suivi', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('Code EpiLogg du forfait, ex. 8.1');
            $table->string('libelle', 150);
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete()
                ->comment('Service auquel la prestation est rattachée (facultatif)');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories_suivi');
    }
};

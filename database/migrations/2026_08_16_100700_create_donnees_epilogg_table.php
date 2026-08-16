<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flux brut EpiLogg : une ligne par prestation réalisée (salarié, jour, forfait).
 *
 * Minimisation RGPD : on ne stocke NI le bénéficiaire NI le contenu de
 * l'intervention — uniquement ce qui sert au calcul de productivité.
 * `reference_externe` (identifiant de la prestation côté EpiLogg) permet
 * un import idempotent ; sa forme sera figée quand le mode d'accès à
 * EpiLogg (API / DB / fichier) sera arrêté avec la Fondation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donnees_epilogg', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('categorie_suivi_id')->constrained('categories_suivi')->restrictOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('quantite')->default(1)->comment('Nombre de forfaits');
            $table->string('reference_externe', 100)->nullable()->comment('Identifiant de la prestation dans EpiLogg');
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->timestamps();

            $table->unique('reference_externe');
            $table->index(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donnees_epilogg');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flux brut DSK — format « long », copie fidèle de GET /accounts/calc/all :
 * une ligne par (salarié, jour, compte DSK) avec la valeur en minutes.
 *
 * La contrainte d'unicité rend l'import IDEMPOTENT : ré-importer la même
 * période met à jour les valeurs (upsert) au lieu de créer des doublons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donnees_dsk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('compte_dsk_id')->constrained('comptes_dsk')->restrictOnDelete();
            $table->date('date');
            $table->integer('minutes')->comment('Valeur du compte pour la journée, en minutes (peut être négative : régularisation)');
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'compte_dsk_id', 'date'], 'donnees_dsk_user_compte_date_unique');
            $table->index(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donnees_dsk');
    }
};

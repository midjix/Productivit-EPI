<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organigramme de la Fondation (Follow Up, AISP, PSY, Administration…).
 * Le code correspond au `codeDept` du référentiel DSK (GET /departments),
 * ce qui permet de rattacher automatiquement les salariés importés à leur service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Identifiant DSK du département (codeDept)');
            $table->string('nom', 100);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

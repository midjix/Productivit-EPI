<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des compteurs DSK (GET /accounts) et leur classification métier.
 *
 * DSK renvoie des minutes par « compte » numéroté (noAccount) sans sémantique
 * exploitable. L'Administrateur classe chaque compte (normal, formation, congé…)
 * et indique s'il entre dans les heures effectives — le dénominateur du ratio.
 * Un nouveau compte créé côté DSK apparaît ici à l'import suivant, en `autre`
 * et hors effectif, en attendant sa qualification : aucun redéploiement requis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comptes_dsk', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('no_account')->unique()->comment('Identifiant DSK du compte (noAccount)');
            $table->string('libelle', 150);
            $table->unsignedSmallInteger('type_dsk')->nullable()->comment('Type DSK brut (ABSENCE / SYSTEM)');
            $table->string('categorie', 20)->default('autre')
                ->comment('normal | formation | conge | maladie | deplacement | autre (App\Enums\CategorieHeure)');
            $table->boolean('compte_effectif')->default(false)
                ->comment('Vrai si les minutes de ce compte entrent dans les heures de travail effectives');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comptes_dsk');
    }
};

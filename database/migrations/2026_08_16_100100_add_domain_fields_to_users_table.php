<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit la table `users` fournie par Laravel avec les attributs métier
 * de l'entité `Utilisateur` du diagramme de classes.
 *
 * Point sensible (cf. mémoire) : il n'existe pas d'identifiant unique commun
 * entre DSK et EpiLogg. On stocke donc séparément :
 *  - `dsk_no_pers` : identifiant technique DSK (noPers) ;
 *  - `matricule`   : identifiant « humain » (codePers DSK), pivot de jointure
 *                    pressenti avec EpiLogg — à confirmer avec la Fondation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule', 30)->nullable()->unique()->after('email')
                ->comment('Matricule RH (codePers DSK) — pivot de jointure DSK/EpiLogg');
            $table->unsignedInteger('dsk_no_pers')->nullable()->unique()->after('matricule')
                ->comment('Identifiant technique du salarié dans DSK (noPers)');
            $table->foreignId('service_id')->nullable()->after('dsk_no_pers')
                ->constrained('services')->nullOnDelete();
            $table->string('role', 20)->default('salarie')->after('service_id')
                ->comment('salarie | coordinateur | administrateur (App\Enums\Role)');
            $table->boolean('actif')->default(true)->after('role');
            $table->date('date_entree')->nullable()->after('actif');
            $table->date('date_sortie')->nullable()->after('date_entree');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropIndex(['role']);
            $table->dropColumn(['matricule', 'dsk_no_pers', 'role', 'actif', 'date_entree', 'date_sortie']);
        });
    }
};

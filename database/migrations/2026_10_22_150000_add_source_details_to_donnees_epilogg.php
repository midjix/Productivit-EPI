<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Détails issus du schéma RÉEL d'EpiLogg (relevé du 22/10/2026, table `benefits`) :
 *  - `travel_package_used` : forfait déplacement (booléen) saisi en plus des forfaits de service ;
 *  - `service_duration` / `travel_duration` : durées en minutes saisies par le salarié.
 *
 * On conserve ces valeurs brutes (fidélité ETL) : elles permettent à l'Administrateur
 * de contrôler la cohérence « forfaits × barème » vs « durée saisie », et de décider
 * plus tard comment valoriser les déplacements (règle métier à confirmer avec la Fondation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donnees_epilogg', function (Blueprint $table) {
            $table->boolean('forfait_deplacement')->default(false)->after('quantite')
                ->comment('EpiLogg travel_package_used : un forfait déplacement a été saisi');
            $table->unsignedSmallInteger('minutes_service_source')->nullable()->after('forfait_deplacement')
                ->comment('EpiLogg service_duration : durée saisie, pour contrôle de cohérence');
            $table->unsignedSmallInteger('minutes_deplacement_source')->nullable()->after('minutes_service_source')
                ->comment('EpiLogg travel_duration : durée de déplacement saisie');
        });
    }

    public function down(): void
    {
        Schema::table('donnees_epilogg', function (Blueprint $table) {
            $table->dropColumn(['forfait_deplacement', 'minutes_service_source', 'minutes_deplacement_source']);
        });
    }
};

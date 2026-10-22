<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Point d'entrée `php artisan db:seed`.
 *
 *  - ReferentielSeeder : tarifs, barème initial, organigramme — tous environnements, idempotent.
 *  - DemoSeeder        : données fictives de démonstration — local/testing uniquement.
 *
 * En production : `php artisan db:seed --class=ReferentielSeeder` (ou `db:seed`, le DemoSeeder
 * se désactive de lui-même hors local/testing).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ReferentielSeeder::class,
            DemoSeeder::class,
        ]);
    }
}

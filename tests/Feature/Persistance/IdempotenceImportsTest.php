<?php

namespace Tests\Feature\Persistance;

use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les contraintes d'unicité qui rendent les imports idempotents sont portées
 * par MariaDB, pas seulement par le code : ré-importer ne duplique jamais.
 */
class IdempotenceImportsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function une_prestation_epilogg_ne_peut_pas_etre_importee_deux_fois(): void
    {
        $premiere = DonneeEpilogg::factory()->create(['reference_externe' => 'B-4242']);

        $this->expectException(UniqueConstraintViolationException::class);

        DonneeEpilogg::factory()->create([
            'user_id' => $premiere->user_id,
            'categorie_suivi_id' => $premiere->categorie_suivi_id,
            'reference_externe' => 'B-4242',
        ]);
    }

    #[Test]
    public function une_prestation_sans_reference_externe_est_refusee(): void
    {
        $this->expectException(QueryException::class);

        DonneeEpilogg::factory()->create(['reference_externe' => null]);
    }

    #[Test]
    public function la_quantite_de_forfaits_conserve_ses_decimales(): void
    {
        $prestation = DonneeEpilogg::factory()->create(['quantite' => 1.5]);

        $this->assertSame('1.50', (string) $prestation->fresh()->quantite);
    }

    #[Test]
    public function un_meme_compteur_dsk_ne_peut_avoir_deux_valeurs_le_meme_jour(): void
    {
        $user = User::factory()->create();
        $compte = CompteDsk::factory()->create();

        DonneeDsk::factory()->create(['user_id' => $user->id, 'compte_dsk_id' => $compte->id, 'date' => '2026-03-02', 'minutes' => 480]);

        $this->expectException(UniqueConstraintViolationException::class);

        DonneeDsk::factory()->create(['user_id' => $user->id, 'compte_dsk_id' => $compte->id, 'date' => '2026-03-02', 'minutes' => 420]);
    }

    #[Test]
    public function un_upsert_met_a_jour_au_lieu_de_dupliquer(): void
    {
        $user = User::factory()->create();
        $compte = CompteDsk::factory()->create();
        $cle = ['user_id' => $user->id, 'compte_dsk_id' => $compte->id, 'date' => '2026-03-02'];

        DonneeDsk::upsert([$cle + ['minutes' => 480]], ['user_id', 'compte_dsk_id', 'date'], ['minutes']);
        DonneeDsk::upsert([$cle + ['minutes' => 420]], ['user_id', 'compte_dsk_id', 'date'], ['minutes']);

        $this->assertSame(1, DonneeDsk::count());
        $this->assertSame(420, DonneeDsk::first()->minutes);
    }
}

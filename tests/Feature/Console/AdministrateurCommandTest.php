<?php

namespace Tests\Feature\Console;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** `php artisan epi:administrateur` : premier Administrateur après une installation ou un import réel. */
class AdministrateurCommandTest extends TestCase
{
    use RefreshDatabase;

    private const MDP = 'MotDePasse2026!';

    #[Test]
    public function promeut_un_compte_importe_et_definit_son_mot_de_passe(): void
    {
        $user = User::factory()->create(['email' => 'michel@epi.test', 'actif' => false]);
        $user->createToken('ancien');

        $this->artisan('epi:administrateur', ['email' => 'Michel@EPI.test', '--mot-de-passe' => self::MDP])
            ->expectsOutputToContain('michel@epi.test mis à jour')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame(Role::Administrateur, $user->role);
        $this->assertTrue($user->actif);
        $this->assertTrue(Hash::check(self::MDP, $user->password));
        $this->assertSame(0, $user->tokens()->count(), 'les anciens jetons sont révoqués');
        $this->assertSame(1, User::count(), 'aucun doublon malgré la casse de l\'email');

        $this->postJson('/api/login', ['email' => 'michel@epi.test', 'password' => self::MDP])
            ->assertCreated()
            ->assertJsonPath('user.role', 'administrateur');
    }

    #[Test]
    public function cree_un_compte_technique_quand_l_email_est_inconnu(): void
    {
        $this->artisan('epi:administrateur', ['email' => 'admin@epi.test', '--nom' => 'Compte technique', '--mot-de-passe' => self::MDP])
            ->expectsOutputToContain('admin@epi.test créé')
            ->expectsOutputToContain('sans lien avec DSK')
            ->assertSuccessful();

        $user = User::where('email', 'admin@epi.test')->firstOrFail();
        $this->assertSame(Role::Administrateur, $user->role);
        $this->assertNull($user->dsk_no_pers);
        $this->assertNull($user->service_id);
    }

    #[Test]
    public function demande_le_mot_de_passe_de_facon_masquee_quand_il_n_est_pas_fourni(): void
    {
        User::factory()->create(['email' => 'michel@epi.test']);

        $this->artisan('epi:administrateur', ['email' => 'michel@epi.test'])
            ->expectsQuestion('Mot de passe (12 caractères, minuscule, majuscule, chiffre, symbole)', self::MDP)
            ->assertSuccessful();

        $this->assertTrue(Hash::check(self::MDP, User::firstOrFail()->password));
    }

    #[Test]
    public function refuse_un_mot_de_passe_hors_politique_sans_rien_modifier(): void
    {
        $user = User::factory()->create(['email' => 'michel@epi.test']);

        $this->artisan('epi:administrateur', ['email' => 'michel@epi.test', '--mot-de-passe' => 'court'])
            ->expectsOutputToContain('Mot de passe refusé')
            ->assertExitCode(2);

        $this->assertSame(Role::Salarie, $user->refresh()->role);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    #[Test]
    public function refuse_une_adresse_invalide_et_un_nouveau_compte_sans_nom(): void
    {
        $this->artisan('epi:administrateur', ['email' => 'pas-un-email', '--mot-de-passe' => self::MDP])->assertExitCode(2);

        $this->artisan('epi:administrateur', ['email' => 'inconnu@epi.test', '--mot-de-passe' => self::MDP])
            ->expectsQuestion('Compte inconnu : nom affiché du nouveau compte', '')
            ->assertExitCode(2);

        $this->assertSame(0, User::count());
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cas d'utilisation « Gérer l'organigramme et les affectations » : services, rôles, activité, email, mot de passe initial. */
class OrganigrammeAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $psy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->psy = Service::factory()->create(['code' => 'PSY', 'nom' => 'Service Psychologique']);
        $this->admin = User::factory()->administrateur()->create(['service_id' => $this->psy->id, 'name' => 'Zoé ADMIN']);
        Sanctum::actingAs($this->admin);
    }

    #[Test]
    public function les_utilisateurs_sont_listes_avec_leurs_affectations_et_filtrables(): void
    {
        $aisp = Service::factory()->create(['code' => 'AISP', 'nom' => 'AISP']);
        User::factory()->create(['name' => 'Alice MARTIN', 'service_id' => $aisp->id]);
        User::factory()->sorti()->create(['name' => 'Bruno PARTI', 'service_id' => $aisp->id]);
        User::factory()->sansEpilogg()->create(['name' => 'Chloé SANS', 'service_id' => $this->psy->id]);

        $this->getJson('/api/admin/utilisateurs')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', 'Alice MARTIN')
            ->assertJsonPath('data.0.service.code', 'AISP')
            ->assertJsonPath('data.0.apparie_epilogg', true)
            ->assertJsonPath('data.1.peut_se_connecter', false)
            ->assertJsonPath('data.2.apparie_epilogg', false)
            ->assertJsonMissingPath('data.0.dsk_no_pers')
            ->assertJsonMissingPath('data.0.password');

        $this->getJson("/api/admin/utilisateurs?service_id={$aisp->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/utilisateurs?actif=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bruno PARTI');
        $this->getJson('/api/admin/utilisateurs?role=administrateur')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/utilisateurs?q=chlo')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/utilisateurs?role=patron')->assertUnprocessable();
    }

    #[Test]
    public function l_administrateur_affecte_un_role_un_service_et_l_email_d_appariement(): void
    {
        $salarie = User::factory()->create(['service_id' => $this->psy->id]);
        $aisp = Service::factory()->create(['code' => 'AISP']);

        $this->patchJson("/api/admin/utilisateurs/{$salarie->id}", ['role' => 'coordinateur', 'service_id' => $aisp->id, 'email' => 'Nouveau.Mail@fondationepi.lu'])
            ->assertOk()
            ->assertJsonPath('role', 'coordinateur')
            ->assertJsonPath('service.code', 'AISP')
            ->assertJsonPath('email', 'Nouveau.Mail@fondationepi.lu');

        $this->assertSame(Role::Coordinateur, $salarie->fresh()->role);

        // Email déjà pris par un autre compte : refusé.
        $this->patchJson("/api/admin/utilisateurs/{$salarie->id}", ['email' => $this->admin->email])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson("/api/admin/utilisateurs/{$salarie->id}", ['role' => 'patron'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->patchJson("/api/admin/utilisateurs/{$salarie->id}", ['service_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    #[Test]
    public function desactiver_un_compte_revoque_ses_jetons(): void
    {
        $salarie = User::factory()->create();
        $salarie->createToken('spa');
        $salarie->createToken('mobile');

        $this->patchJson("/api/admin/utilisateurs/{$salarie->id}", ['actif' => false])->assertOk()->assertJsonPath('actif', false)->assertJsonPath('peut_se_connecter', false);

        $this->assertSame(0, $salarie->tokens()->count());
    }

    #[Test]
    public function un_administrateur_ne_peut_ni_se_retrograder_ni_se_desactiver(): void
    {
        $this->patchJson("/api/admin/utilisateurs/{$this->admin->id}", ['role' => 'salarie'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->patchJson("/api/admin/utilisateurs/{$this->admin->id}", ['actif' => false])->assertUnprocessable()->assertJsonValidationErrors('actif');
        // Changer son propre service reste possible.
        $this->patchJson("/api/admin/utilisateurs/{$this->admin->id}", ['service_id' => null])->assertOk()->assertJsonPath('service', null);

        $this->assertSame(Role::Administrateur, $this->admin->fresh()->role);
        $this->assertTrue($this->admin->fresh()->actif);
    }

    #[Test]
    public function definir_un_mot_de_passe_initial_permet_la_connexion_et_revoque_les_anciens_jetons(): void
    {
        $salarie = User::factory()->create(['email' => 'nouveau@fondationepi.lu', 'password' => 'ancien-mot-de-passe-aleatoire-1']);
        $salarie->createToken('spa');

        $this->putJson("/api/admin/utilisateurs/{$salarie->id}/mot-de-passe", ['password' => 'court'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson("/api/admin/utilisateurs/{$salarie->id}/mot-de-passe", ['password' => 'MotDePasse2026!', 'password_confirmation' => 'autre'])->assertUnprocessable();
        $this->putJson("/api/admin/utilisateurs/{$salarie->id}/mot-de-passe", ['password' => 'MotDePasse2026!', 'password_confirmation' => 'MotDePasse2026!'])->assertNoContent();

        $this->assertSame(0, $salarie->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'nouveau@fondationepi.lu', 'password' => 'MotDePasse2026!'])->assertCreated();
    }

    #[Test]
    public function les_services_se_listent_et_se_renomment_sans_toucher_au_code_dsk(): void
    {
        Service::factory()->create(['code' => 'OLD', 'nom' => 'Ancien service', 'actif' => false]);

        $this->getJson('/api/admin/services')->assertOk()->assertJsonCount(2)->assertJsonPath('0.code', 'OLD')->assertJsonPath('0.actif', false)->assertJsonPath('1.nb_utilisateurs', 1);

        $this->patchJson("/api/admin/services/{$this->psy->id}", ['nom' => 'Psychologie', 'actif' => true])
            ->assertOk()->assertJsonPath('nom', 'Psychologie')->assertJsonPath('code', 'PSY');

        $this->patchJson("/api/admin/services/{$this->psy->id}", ['code' => 'HACK'])->assertUnprocessable();
        $this->patchJson("/api/admin/services/{$this->psy->id}", ['nom' => 'X'])->assertUnprocessable()->assertJsonValidationErrors('nom');
        $this->assertSame('PSY', $this->psy->fresh()->code);
    }
}

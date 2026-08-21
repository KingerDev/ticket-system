<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Prístupové práva: prihlásenie, bežný admin vs. super admin. */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function adminRoutes(): array
    {
        return [
            'prehľad'        => ['dashboard'],
            'registrácie'    => ['admin.registrations.index'],
            'usádzač'        => ['admin.seating'],
            'check-in'       => ['admin.checkin'],
            'export'         => ['admin.export'],
            'mapa stolov'    => ['admin.tables.map'],
        ];
    }

    public static function superAdminRoutes(): array
    {
        return [
            'používatelia'   => ['admin.users.index'],
            'záznam činnosti' => ['admin.activity_log'],
            'konfigurácia sály' => ['admin.hall.edit'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_neprihlaseny_sa_do_administracie_nedostane(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    #[DataProvider('adminRoutes')]
    public function test_prihlaseny_admin_ma_pristup(string $routeName): void
    {
        $this->hall();

        $this->actingAs($this->admin())->get(route($routeName))->assertOk();
    }

    #[DataProvider('superAdminRoutes')]
    public function test_bezny_admin_nema_pristup_k_sprave_uctov(string $routeName): void
    {
        $this->actingAs($this->admin())->get(route($routeName))->assertForbidden();
    }

    #[DataProvider('superAdminRoutes')]
    public function test_super_admin_ma_pristup(string $routeName): void
    {
        $this->actingAs($this->superAdmin())->get(route($routeName))->assertOk();
    }

    #[DataProvider('superAdminRoutes')]
    public function test_neprihlaseny_nema_pristup_ani_k_super_admin_castiam(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    public function test_prihlasenie_spravnym_heslom_prejde_a_zapise_sa(): void
    {
        $user = $this->admin(['email' => 'sef@ef.umb.sk']);

        $this->post(route('login'), [
            'email'    => 'sef@ef.umb.sk',
            'password' => 'HesloHeslo123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', [
            'action'    => 'auth.login',
            'user_id'   => $user->id,
            'user_name' => $user->name,
        ]);
    }

    public function test_prihlasenie_zlym_heslom_neprejde(): void
    {
        $this->admin(['email' => 'sef@ef.umb.sk']);

        $this->post(route('login'), [
            'email'    => 'sef@ef.umb.sk',
            'password' => 'zle-heslo',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, ActivityLog::where('action', 'auth.login')->count());
    }

    public function test_odhlasenie_sa_zapise(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_vlastny_ucet_sa_neda_zmazat(): void
    {
        $user = $this->admin();

        // Cesta DELETE /profile bola zámerne odstránená.
        $this->actingAs($user)->delete('/profile')->assertStatus(405);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_profil_sa_zobrazi(): void
    {
        $this->actingAs($this->admin())->get(route('profile.edit'))->assertOk();
    }

    public function test_super_admin_si_zmeni_meno_aj_email(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name'  => 'Nové Meno',
            'email' => 'nove@ef.umb.sk',
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Nové Meno', $user->name);
        $this->assertSame('nove@ef.umb.sk', $user->email);
    }

    public function test_bezny_admin_si_meno_ani_email_zmenit_nemoze(): void
    {
        $user = $this->admin(['name' => 'Pôvodné Meno', 'email' => 'povodny@ef.umb.sk']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name'  => 'Podvrhnuté Meno',
            'email' => 'podvrh@ef.umb.sk',
        ])->assertForbidden();

        $user->refresh();
        $this->assertSame('Pôvodné Meno', $user->name);
        $this->assertSame('povodny@ef.umb.sk', $user->email);
    }

    public function test_bezny_admin_si_heslo_zmenit_moze(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->put(route('password.update'), [
            'current_password'      => 'HesloHeslo123',
            'password'              => 'UplneIneHeslo1',
            'password_confirmation' => 'UplneIneHeslo1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Auth::validate([
            'email' => $user->email, 'password' => 'UplneIneHeslo1',
        ]));
    }

    public function test_stranka_profilu_hovori_ci_sa_udaje_daju_menit(): void
    {
        $vidiBezny = $this->actingAs($this->admin())
            ->get(route('profile.edit'))->viewData('page')['props']['canEditIdentity'];

        $vidiSpravca = $this->actingAs($this->superAdmin())
            ->get(route('profile.edit'))->viewData('page')['props']['canEditIdentity'];

        $this->assertFalse($vidiBezny);
        $this->assertTrue($vidiSpravca);
    }
}

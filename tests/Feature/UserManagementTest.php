<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Správa administrátorských účtov super administrátorom. */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $sef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sef = $this->superAdmin(['name' => 'Hlavný Organizátor', 'email' => 'sef@ef.umb.sk']);
        $this->actingAs($this->sef);
    }

    public function test_super_admin_zaloz_novy_ucet(): void
    {
        $this->post(route('admin.users.store'), [
            'name'                  => 'Anna Adminová',
            'email'                 => 'anna@ef.umb.sk',
            'password'              => 'HesloHeslo123',
            'password_confirmation' => 'HesloHeslo123',
        ])->assertSessionHas('success');

        $anna = User::where('email', 'anna@ef.umb.sk')->first();
        $this->assertNotNull($anna);
        $this->assertFalse($anna->is_super_admin);
        $this->assertNotNull($anna->email_verified_at, 'nový účet nemá viaznuť na overení e-mailu');
        $this->assertTrue(Auth::validate(['email' => 'anna@ef.umb.sk', 'password' => 'HesloHeslo123']));

        $this->assertDatabaseHas('activity_logs', ['action' => 'user.created']);
    }

    public function test_novy_ucet_moze_byt_rovno_super_admin(): void
    {
        $this->post(route('admin.users.store'), [
            'name' => 'Anna Adminová', 'email' => 'anna@ef.umb.sk',
            'password' => 'HesloHeslo123', 'password_confirmation' => 'HesloHeslo123',
            'is_super_admin' => true,
        ]);

        $this->assertTrue(User::where('email', 'anna@ef.umb.sk')->value('is_super_admin'));
    }

    public function test_validacie_pri_zakladani(): void
    {
        $this->post(route('admin.users.store'), [
            'name' => 'Anna', 'email' => 'anna@ef.umb.sk',
            'password' => '123', 'password_confirmation' => '123',
        ])->assertSessionHasErrors('password');

        $this->post(route('admin.users.store'), [
            'name' => 'Anna', 'email' => 'anna@ef.umb.sk',
            'password' => 'HesloHeslo123', 'password_confirmation' => 'ine-heslo',
        ])->assertSessionHasErrors('password');

        $this->post(route('admin.users.store'), [
            'name' => 'Anna', 'email' => 'sef@ef.umb.sk',
            'password' => 'HesloHeslo123', 'password_confirmation' => 'HesloHeslo123',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_uprava_uctu_a_zmena_hesla(): void
    {
        $anna = $this->admin(['name' => 'Anna Adminová', 'email' => 'anna@ef.umb.sk']);

        $this->patch(route('admin.users.update', $anna), [
            'name'                  => 'Anna Nová',
            'email'                 => 'anna.nova@ef.umb.sk',
            'password'              => 'UplneIneHeslo1',
            'password_confirmation' => 'UplneIneHeslo1',
            'is_super_admin'        => true,
        ])->assertSessionHas('success');

        $anna->refresh();
        $this->assertSame('Anna Nová', $anna->name);
        $this->assertTrue($anna->is_super_admin);
        $this->assertTrue(Auth::validate(['email' => 'anna.nova@ef.umb.sk', 'password' => 'UplneIneHeslo1']));
    }

    public function test_prazdne_heslo_pri_uprave_nechá_povodne(): void
    {
        $anna = $this->admin(['email' => 'anna@ef.umb.sk']);

        $this->patch(route('admin.users.update', $anna), [
            'name' => 'Anna Adminová', 'email' => 'anna@ef.umb.sk', 'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Auth::validate(['email' => 'anna@ef.umb.sk', 'password' => 'HesloHeslo123']));
    }

    public function test_poslednemu_super_adminovi_sa_neda_odobrat_rola(): void
    {
        $this->patch(route('admin.users.update', $this->sef), [
            'name' => $this->sef->name, 'email' => $this->sef->email, 'is_super_admin' => false,
        ])->assertSessionHas('error');

        $this->assertTrue($this->sef->fresh()->is_super_admin);
    }

    public function test_pri_dvoch_super_adminoch_sa_rola_odobrat_da(): void
    {
        $druhy = $this->superAdmin(['email' => 'druhy@ef.umb.sk']);

        $this->patch(route('admin.users.update', $druhy), [
            'name' => $druhy->name, 'email' => $druhy->email, 'is_super_admin' => false,
        ])->assertSessionHas('success');

        $this->assertFalse($druhy->fresh()->is_super_admin);
    }

    public function test_vlastny_ucet_sa_neda_odstranit(): void
    {
        $this->delete(route('admin.users.destroy', $this->sef))->assertSessionHas('error');

        $this->assertNotNull(User::find($this->sef->id));
    }

    public function test_posledny_super_admin_sa_neda_odstranit(): void
    {
        // Odstraňuje iný super admin, aby nešlo o poistku na vlastný účet.
        $druhy = $this->superAdmin(['email' => 'druhy@ef.umb.sk']);
        $this->actingAs($druhy);
        $this->delete(route('admin.users.destroy', $this->sef))->assertSessionHas('success');

        $this->actingAs($this->admin());
        $this->delete(route('admin.users.destroy', $druhy))->assertForbidden();
    }

    public function test_odstranenie_cudzieho_uctu(): void
    {
        $anna = $this->admin(['email' => 'anna@ef.umb.sk']);

        $this->delete(route('admin.users.destroy', $anna))->assertSessionHas('success');

        $this->assertNull(User::find($anna->id));
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deleted']);
    }

    public function test_bezny_admin_nemoze_zakladat_ucty(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.users.store'), [
            'name' => 'Podvodník', 'email' => 'podvod@ef.umb.sk',
            'password' => 'HesloHeslo123', 'password_confirmation' => 'HesloHeslo123',
        ])->assertForbidden();

        $this->assertNull(User::where('email', 'podvod@ef.umb.sk')->first());
    }
}

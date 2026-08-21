<?php

namespace Tests\Feature;

use App\Models\HallConfig;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Zakladanie prvého účtu a sály – beží aj v produkčnom obraze bez dev závislostí. */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    private function spustiSeeder(array $config = []): void
    {
        config(array_merge([
            'admin.name'     => 'Administrátor',
            'admin.email'    => 'admin@ples.sk',
            'admin.password' => 'TajneHeslo1234',
        ], $config));

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
    }

    public function test_seeder_zalozi_super_admina_a_salu(): void
    {
        $this->spustiSeeder();

        $admin = User::where('email', 'admin@ples.sk')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->is_super_admin, 'prvý účet musí byť super admin');
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Auth::validate(['email' => 'admin@ples.sk', 'password' => 'TajneHeslo1234']));

        $this->assertSame(1, HallConfig::count());
        $this->assertSame(12, Table::count());
    }

    public function test_seeder_nepouziva_factory_a_teda_ani_faker(): void
    {
        // Regresia: factory volá fake(), ktorý v produkčnom obraze neexistuje.
        $zdroj = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('factory(', $zdroj);
    }

    public function test_opakovany_seed_neduplikuje(): void
    {
        $this->spustiSeeder();
        $this->spustiSeeder();

        $this->assertSame(1, User::count());
        $this->assertSame(1, HallConfig::count());
        $this->assertSame(12, Table::count());
    }

    public function test_bez_hesla_v_prostredi_sa_vygeneruje(): void
    {
        $this->spustiSeeder(['admin.password' => null]);

        $admin = User::where('email', 'admin@ples.sk')->first();
        $this->assertNotNull($admin);
        $this->assertNotEmpty($admin->password);
    }

    public function test_opakovany_seed_bez_hesla_nechá_povodne(): void
    {
        $this->spustiSeeder();
        $this->spustiSeeder(['admin.password' => null]);

        $this->assertTrue(
            Auth::validate(['email' => 'admin@ples.sk', 'password' => 'TajneHeslo1234']),
            'bez ADMIN_PASSWORD sa heslo nesmie prepísať'
        );
    }

    public function test_vlastny_email_zalozi_dalsi_ucet(): void
    {
        $this->spustiSeeder();
        $this->spustiSeeder(['admin.email' => 'iny@ef.umb.sk']);

        $this->assertSame(2, User::count());
    }
}

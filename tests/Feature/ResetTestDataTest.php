<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\HallConfig;
use App\Models\Registration;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Vyčistenie skúšobných dát pred ostrým spustením registrácií. */
class ResetTestDataTest extends TestCase
{
    use RefreshDatabase;

    private function skusobneData(): void
    {
        $this->hall();
        $this->superAdmin();

        $table = Table::first();
        $this->reservation([
            ['name' => 'Jana Nováková', 'table_id' => $table->id, 'seat_number' => 1, 'paid' => true, 'ticket_code' => '007'],
            ['name' => 'Peter Malý'],
        ]);
        $this->reservation([['name' => 'Eva Krátka']]);

        ActivityLog::record('guest.updated', 'Skúšobná zmena', null, [], User::first());
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'available_at' => time(), 'created_at' => time(),
        ]);
    }

    public function test_prikaz_zmaze_data_a_ponecha_ucty_aj_salu(): void
    {
        $this->skusobneData();

        $this->artisan('ples:reset --force')->assertSuccessful();

        $this->assertSame(0, Registration::count());
        $this->assertSame(0, Guest::count());
        $this->assertSame(0, ActivityLog::count());
        $this->assertSame(0, DB::table('jobs')->count());

        $this->assertSame(1, User::count(), 'účty musia zostať');
        $this->assertSame(12, Table::count(), 'stoly musia zostať');
        $this->assertSame(1, HallConfig::count(), 'rozloženie sály musí zostať');
    }

    public function test_keep_log_ponechá_zaznam_cinnosti(): void
    {
        $this->skusobneData();

        $this->artisan('ples:reset --keep-log --force')->assertSuccessful();

        $this->assertSame(0, Registration::count());
        $this->assertSame(1, ActivityLog::count(), 'záznam sa mal ponechať');
    }

    public function test_odmietnutie_potvrdenia_nic_nezmaze(): void
    {
        $this->skusobneData();

        $this->artisan('ples:reset')
            ->expectsConfirmation('Naozaj zmazať? Vrátiť sa to nedá.', 'no')
            ->assertFailed();

        $this->assertSame(2, Registration::count(), 'bez potvrdenia sa nesmie nič stratiť');
        $this->assertSame(3, Guest::count());
    }

    public function test_na_cistej_databaze_nerobi_nic(): void
    {
        $this->hall();
        $this->superAdmin();

        $this->artisan('ples:reset')
            ->expectsOutputToContain('Niet čo mazať')
            ->assertSuccessful();
    }

    public function test_cislovanie_rezervacii_zacne_odznova(): void
    {
        $this->skusobneData();
        $this->artisan('ples:reset --force');

        $nova = $this->reservation([['name' => 'Nová Hostka']], ['reservation_number' => 'DOCASNE']);
        $this->assertSame(1, $nova->id, 'počítadlo ID sa má vynulovať');
    }
}

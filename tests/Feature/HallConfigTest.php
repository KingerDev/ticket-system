<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\HallConfig;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Zmena rozloženia sály – pridávanie a rušenie stolov, kapacita. */
class HallConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->superAdmin());
    }

    public function test_prve_ulozenie_vytvori_konfiguraciu_aj_stoly(): void
    {
        $this->post(route('admin.hall.update'), [
            'num_rows'        => 2,
            'tables_per_row'  => [3, 2],
            'seats_per_table' => 6,
        ])->assertRedirect(route('admin.hall.edit'));

        $this->assertSame(1, HallConfig::count());
        $this->assertSame(5, Table::count());
        $this->assertSame(
            ['A1', 'A2', 'A3', 'B1', 'B2'],
            Table::orderBy('name')->pluck('name')->all()
        );
        $this->assertSame([6], Table::distinct()->pluck('capacity')->all());
    }

    public function test_pridanie_stolov_zachova_povodne(): void
    {
        $this->hall(2, 2, 8);
        $povodneId = Table::where('name', 'A1')->value('id');

        $this->post(route('admin.hall.update'), [
            'num_rows'        => 2,
            'tables_per_row'  => [4, 2],
            'seats_per_table' => 8,
        ]);

        $this->assertSame(6, Table::count());
        $this->assertSame($povodneId, Table::where('name', 'A1')->value('id'), 'existujúci stôl sa nemá znovu vytvárať');
    }

    public function test_zrusenie_stola_uvolni_hostov(): void
    {
        $this->hall(2, 2, 8);
        $stol = Table::where('name', 'B2')->first();
        $guest = $this->reservation([[
            'name' => 'Jana Nováková', 'table_id' => $stol->id, 'seat_number' => 3,
        ]])->guests->first();

        $this->post(route('admin.hall.update'), [
            'num_rows'        => 2,
            'tables_per_row'  => [2, 1],
            'seats_per_table' => 8,
        ]);

        $this->assertNull(Table::where('name', 'B2')->first(), 'stôl sa mal zrušiť');

        $guest->refresh();
        $this->assertNull($guest->table_id, 'hosťovi sa má zrušiť väzba na stôl');
        $this->assertNull($guest->seat_number, 'hosťovi sa má vynulovať číslo miesta');
        $this->assertNotNull(Guest::find($guest->id), 'samotný hosť sa mazať nesmie');
    }

    public function test_zmena_kapacity_sa_prejavi_na_vsetkych_stoloch(): void
    {
        $this->hall(2, 2, 8);

        $this->post(route('admin.hall.update'), [
            'num_rows'        => 2,
            'tables_per_row'  => [2, 2],
            'seats_per_table' => 10,
        ]);

        $this->assertSame([10], Table::distinct()->pluck('capacity')->all());
    }

    public function test_zmena_saly_sa_zapise_do_auditu(): void
    {
        $this->hall(2, 2, 8);

        $this->post(route('admin.hall.update'), [
            'num_rows'        => 2,
            'tables_per_row'  => [3, 2],
            'seats_per_table' => 8,
        ]);

        $log = \App\Models\ActivityLog::where('action', 'hall.updated')->first();
        $this->assertNotNull($log);
        $this->assertSame(['A3'], $log->properties['pridané']);
        $this->assertSame([], $log->properties['odobraté']);
    }

    public function test_neplatne_hodnoty_neprejdu(): void
    {
        $this->post(route('admin.hall.update'), [
            'num_rows' => 0, 'tables_per_row' => [1], 'seats_per_table' => 8,
        ])->assertSessionHasErrors('num_rows');

        $this->post(route('admin.hall.update'), [
            'num_rows' => 2, 'tables_per_row' => [1, 1], 'seats_per_table' => 0,
        ])->assertSessionHasErrors('seats_per_table');

        $this->post(route('admin.hall.update'), [
            'num_rows' => 99, 'tables_per_row' => [1], 'seats_per_table' => 8,
        ])->assertSessionHasErrors('num_rows');
    }

    public function test_mapa_stolov_zostava_pristupna_aj_beznemu_adminovi(): void
    {
        $this->hall(2, 2, 8);

        $this->actingAs($this->admin())->get(route('admin.tables.map'))->assertOk();
    }

    public function test_bezny_admin_nesmie_menit_rozlozenie_saly(): void
    {
        $this->hall(2, 2, 8);
        $this->actingAs($this->admin());

        $this->get(route('admin.hall.edit'))->assertForbidden();

        $this->post(route('admin.hall.update'), [
            'num_rows' => 1, 'tables_per_row' => [1], 'seats_per_table' => 8,
        ])->assertForbidden();

        $this->assertSame(4, Table::count(), 'stoly sa nesmú zmeniť');
    }
}

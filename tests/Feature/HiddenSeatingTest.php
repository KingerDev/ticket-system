<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Admin bez usádzania: vidí registrácie a obsadenosť, nie to, kto kde sedí. */
class HiddenSeatingTest extends TestCase
{
    use RefreshDatabase;

    private function limited(): User
    {
        return $this->admin(['hide_seating' => true]);
    }

    public static function seatingRoutes(): array
    {
        return [
            'usádzač' => ['admin.seating'],
        ];
    }

    #[DataProvider('seatingRoutes')]
    public function test_nevidi_usadzac(string $routeName): void
    {
        $this->hall();

        $this->actingAs($this->limited())->get(route($routeName))
            ->assertForbidden()
            ->assertDontSee('bez usádzania');
    }

    public function test_bezne_stranky_vidi(): void
    {
        $this->seatedGuest();

        foreach (['dashboard', 'admin.registrations.index', 'admin.checkin', 'admin.export', 'admin.waitlist.index', 'admin.reminders.index'] as $name) {
            $this->actingAs($this->limited())->get(route($name))->assertOk();
        }
    }

    public function test_mapa_ukaze_obsadene_a_rezervovane_bez_mien(): void
    {
        $guest = $this->seatedGuest();
        \App\Models\SeatBlock::create(['table_id' => $guest->table_id, 'seat_number' => 1, 'label' => 'Učiteľ – doc. Novák']);

        $response = $this->actingAs($this->limited())->get(route('admin.tables.map'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tables.0.guests', [['seat_number' => 4]])
                ->where('tables.0.seat_blocks', [['seat_number' => 1]]));

        $response->assertDontSee('Jana Nováková')->assertDontSee('doc. Novák');
    }

    public function test_nevie_usadit_hosta(): void
    {
        $this->hall();
        $registration = $this->reservation();
        $guest = $registration->guests->first();

        $this->actingAs($this->limited())->post(route('admin.registrations.assign', $registration->id), [
            'guest_id' => $guest->id,
            'table_id' => Table::first()->id,
            'seat_number' => 1,
        ])->assertForbidden();

        $this->assertNull($guest->fresh()->table_id);
    }

    public function test_nevie_rezervovat_stolicky(): void
    {
        $this->hall();

        $this->actingAs($this->limited())->post(route('admin.seat_blocks.store'), [
            'table_id' => Table::first()->id, 'seat_number' => 1,
        ])->assertForbidden();
    }

    public function test_zoznam_a_detail_neposielaju_stol_ani_miesto(): void
    {
        $guest = $this->seatedGuest();

        $this->actingAs($this->limited())->get(route('admin.registrations.index'))
            ->assertInertia(fn ($page) => $page
                ->where('guests.data.0.name', 'Jana Nováková')
                ->where('guests.data.0.has_seat', true)
                ->missing('guests.data.0.table')
                ->missing('guests.data.0.table_id')
                ->missing('guests.data.0.seat_number')
                ->where('auth.seating', false)
                ->missing('auth.user.hide_seating'));

        $this->actingAs($this->limited())->get(route('admin.registrations.show', $guest->registration_id))
            ->assertInertia(fn ($page) => $page
                ->where('tables', [])
                ->where('registration.guests.0.has_seat', true)
                ->missing('registration.guests.0.table')
                ->missing('registration.guests.0.seat_number'));
    }

    public function test_bezny_admin_stol_a_miesto_vidi(): void
    {
        $guest = $this->seatedGuest();

        $this->actingAs($this->admin())->get(route('admin.registrations.show', $guest->registration_id))
            ->assertInertia(fn ($page) => $page
                ->where('auth.seating', true)
                ->where('registration.guests.0.seat_number', 4)
                ->has('tables', 12));
    }

    public function test_check_in_neukaze_stol(): void
    {
        $this->seatedGuest('123');

        $this->actingAs($this->limited())->post(route('admin.checkin.store'), ['ticket_code' => '123'])
            ->assertSessionHas('success_guest', fn ($g) => $g['name'] === 'Jana Nováková'
                && $g['table'] === null && $g['seat'] === null);

        $this->assertTrue(Guest::where('ticket_code', '123')->value('checked_in'));
    }

    public function test_vie_vydat_listok_hostovi_s_miestom(): void
    {
        $guest = $this->seatedGuest('', ['ticket_code' => null, 'ticket_issued' => false]);

        $this->actingAs($this->limited())->post(route('admin.guests.issue_ticket', $guest->id))
            ->assertSessionHas('ticket_issued_name', 'Jana Nováková');

        $this->assertTrue($guest->fresh()->ticket_issued);
    }

    public function test_export_bez_stola_a_bez_triedenia_podla_stolov(): void
    {
        $this->seatedGuest();

        $this->actingAs($this->limited())->get(route('admin.export.download', [
            'sort_by' => 'table', 'format' => 'excel',
        ]))->assertSessionHasErrors('sort_by');

        // PDF je komprimované, obsah tu nekontrolujeme – stĺpec vypína ExportController.
        $this->actingAs($this->limited())->get(route('admin.export.download', [
            'sort_by' => 'name', 'format' => 'pdf', 'include_seat' => '1',
        ]))->assertOk();
    }

    public function test_super_admin_vidi_usadenie_aj_s_priznakom(): void
    {
        $this->hall();

        $this->actingAs($this->superAdmin(['hide_seating' => true]))->get(route('admin.seating'))->assertOk();
    }

    public function test_super_admin_nastavi_rolu(): void
    {
        $sef = $this->superAdmin();

        $this->actingAs($sef)->post(route('admin.users.store'), [
            'name' => 'Anna Pomocná', 'email' => 'anna@ef.umb.sk',
            'password' => 'HesloHeslo123', 'password_confirmation' => 'HesloHeslo123',
            'hide_seating' => true,
        ])->assertSessionHasNoErrors();

        $anna = User::where('email', 'anna@ef.umb.sk')->first();
        $this->assertTrue($anna->hide_seating);

        $this->actingAs($sef)->get(route('admin.users.index'))
            ->assertInertia(fn ($page) => $page->where('users.0.hide_seating', true));

        $this->actingAs($sef)->patch(route('admin.users.update', $anna), [
            'name' => $anna->name, 'email' => $anna->email, 'hide_seating' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($anna->fresh()->hide_seating);
    }

    public function test_super_admin_nemoze_byt_bez_usadzania(): void
    {
        $this->actingAs($this->superAdmin())->post(route('admin.users.store'), [
            'name' => 'Anna Pomocná', 'email' => 'anna@ef.umb.sk',
            'password' => 'HesloHeslo123', 'password_confirmation' => 'HesloHeslo123',
            'is_super_admin' => true, 'hide_seating' => true,
        ]);

        $this->assertFalse(User::where('email', 'anna@ef.umb.sk')->value('hide_seating'));
    }
}

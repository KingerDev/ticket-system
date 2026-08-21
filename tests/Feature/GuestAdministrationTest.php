<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Registration;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Správa hostí: úprava údajov, miesta, platby, lístky, odstraňovanie. */
class GuestAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hall();
        $this->actingAs($this->admin());
    }

    // --- úprava údajov ----------------------------------------------------

    public function test_admin_upravi_udaje_hosta(): void
    {
        $guest = $this->reservation([[
            'name' => 'Jana Nováková', 'email' => 'stara@email.sk',
            'allergen_ids' => [1, 7], 'is_vegan' => true,
        ]])->guests->first();

        $this->patch(route('admin.guests.update', $guest), [
            'name'           => 'Jana Kováčová',
            'email'          => 'nova@email.sk',
            'allergen_ids'   => [3],
            'is_vegetarian'  => true,
            'is_teacher'     => true,
            'allergen_note'  => 'celiakia',
            'note'           => 'bezbariérový prístup',
        ])->assertSessionHasNoErrors();

        $guest->refresh();
        $this->assertSame('Jana Kováčová', $guest->name);
        $this->assertSame('nova@email.sk', $guest->email);
        $this->assertSame([3], $guest->allergen_ids);
        $this->assertFalse($guest->is_vegan);
        $this->assertTrue($guest->is_vegetarian);
        $this->assertTrue($guest->is_teacher);
    }

    public function test_uprava_nesiaha_na_miesto_platbu_ani_listok(): void
    {
        $guest = $this->seatedGuest('123', ['paid' => true]);

        $this->patch(route('admin.guests.update', $guest), ['name' => 'Jana Kováčová']);

        $guest->refresh();
        $this->assertTrue($guest->paid, 'platba sa nesmie stratiť');
        $this->assertSame(4, $guest->seat_number, 'miesto sa nesmie stratiť');
        $this->assertSame('123', $guest->ticket_code, 'lístok sa nesmie stratiť');
        $this->assertTrue($guest->ticket_issued);
    }

    public function test_uprava_pouziva_rovnake_pravidla_ako_verejny_formular(): void
    {
        $guest = $this->reservation()->guests->first();

        $this->patch(route('admin.guests.update', $guest), ['name' => 'Jana'])
            ->assertSessionHasErrors('name');

        $this->patch(route('admin.guests.update', $guest), ['name' => 'Jana Nováková', 'email' => 'zle'])
            ->assertSessionHasErrors('email');

        $this->patch(route('admin.guests.update', $guest), ['name' => 'Jana Nováková', 'allergen_ids' => [99]])
            ->assertSessionHasErrors('allergen_ids.0');
    }

    public function test_odskrtnutie_vsetkeho_vyprazdni_polia(): void
    {
        $guest = $this->reservation([[
            'name' => 'Jana Nováková', 'allergen_ids' => [1, 2], 'is_vegan' => true, 'note' => 'nieco',
        ]])->guests->first();

        $this->patch(route('admin.guests.update', $guest), ['name' => 'Jana Nováková']);

        $guest->refresh();
        $this->assertSame([], $guest->allergen_ids);
        $this->assertFalse($guest->is_vegan);
        $this->assertNull($guest->note);
    }

    public function test_admin_upravi_kontakt_rezervacie(): void
    {
        $registration = $this->reservation();

        $this->patch(route('admin.registrations.update_contact', $registration->id), [
            'registrant_name'  => 'Peter Malý',
            'registrant_email' => 'peter@email.sk',
        ])->assertSessionHasNoErrors();

        $registration->refresh();
        $this->assertSame('Peter Malý', $registration->registrant_name);
        $this->assertSame('peter@email.sk', $registration->registrant_email);
    }

    // --- miesta -----------------------------------------------------------

    public function test_pridelenie_miesta(): void
    {
        $registration = $this->reservation();
        $guest = $registration->guests->first();
        $table = Table::first();

        $this->post(route('admin.registrations.assign', $registration->id), [
            'guest_id'    => $guest->id,
            'table_id'    => $table->id,
            'seat_number' => 5,
        ])->assertSessionHas('success');

        $guest->refresh();
        $this->assertSame($table->id, $guest->table_id);
        $this->assertSame(5, $guest->seat_number);
    }

    public function test_obsadene_miesto_sa_nepridelí_dvakrat(): void
    {
        $table = Table::first();
        $prvy = $this->reservation([['name' => 'Jana Nováková', 'table_id' => $table->id, 'seat_number' => 5]]);
        $druhy = $this->reservation([['name' => 'Peter Malý']]);
        $guest = $druhy->guests->first();

        $this->post(route('admin.registrations.assign', $druhy->id), [
            'guest_id'    => $guest->id,
            'table_id'    => $table->id,
            'seat_number' => 5,
        ])->assertSessionHas('error');

        $this->assertNull($guest->fresh()->table_id);
    }

    // --- platby a lístky --------------------------------------------------

    public function test_prepnutie_platby_tam_aj_spat(): void
    {
        $guest = $this->reservation()->guests->first();

        $this->post(route('admin.guests.toggle_paid', $guest));
        $guest->refresh();
        $this->assertTrue($guest->paid);
        $this->assertNotNull($guest->paid_at);

        $this->post(route('admin.guests.toggle_paid', $guest));
        $guest->refresh();
        $this->assertFalse($guest->paid);
        $this->assertNull($guest->paid_at);
    }

    public function test_listok_sa_neda_vydat_bez_miesta(): void
    {
        $guest = $this->reservation()->guests->first();

        $this->post(route('admin.guests.issue_ticket', $guest))->assertSessionHas('error');

        $this->assertFalse($guest->fresh()->ticket_issued);
    }

    public function test_vydanie_listka_pridelí_trojmiestny_kod(): void
    {
        $table = Table::first();
        $guest = $this->reservation([['name' => 'Jana Nováková', 'table_id' => $table->id, 'seat_number' => 2]])->guests->first();

        $this->post(route('admin.guests.issue_ticket', $guest), ['is_teacher' => true])
            ->assertSessionHas('success');

        $guest->refresh();
        $this->assertTrue($guest->ticket_issued);
        $this->assertTrue($guest->is_teacher);
        $this->assertMatchesRegularExpression('/^\d{3}$/', $guest->ticket_code);
    }

    public function test_opakovane_vydanie_nezmeni_kod_listka(): void
    {
        $table = Table::first();
        $guest = $this->reservation([['name' => 'Jana Nováková', 'table_id' => $table->id, 'seat_number' => 2]])->guests->first();

        $this->post(route('admin.guests.issue_ticket', $guest));
        $kod = $guest->fresh()->ticket_code;

        $this->post(route('admin.guests.issue_ticket', $guest));
        $this->assertSame($kod, $guest->fresh()->ticket_code);
    }

    // --- odstraňovanie ----------------------------------------------------

    public function test_odstranenie_hosta_uvolni_miesto(): void
    {
        $table = Table::first();
        $registration = $this->reservation([
            ['name' => 'Jana Nováková', 'table_id' => $table->id, 'seat_number' => 3],
            ['name' => 'Peter Malý'],
        ]);
        $guest = $registration->guests->first();

        $this->delete(route('admin.guests.destroy', $guest))->assertSessionHas('success');

        $this->assertSame(0, Guest::where('table_id', $table->id)->where('seat_number', 3)->count());
        $this->assertSame(1, Guest::count());
        $this->assertSame(1, Registration::count(), 'rezervácia s ďalším hosťom musí zostať');
    }

    public function test_odstranenie_posledneho_hosta_zmaze_aj_rezervaciu(): void
    {
        $registration = $this->reservation([['name' => 'Jana Nováková']]);
        $guest = $registration->guests->first();

        $this->delete(route('admin.guests.destroy', $guest))
            ->assertRedirect(route('admin.registrations.index'));

        $this->assertSame(0, Guest::count());
        $this->assertSame(0, Registration::count());
    }

    public function test_odstranenie_neexistujuceho_hosta_vrati_404(): void
    {
        $this->delete(route('admin.guests.destroy', 9999))->assertNotFound();
    }

    public function test_stoly_zostanu_po_odstraneni_hosta_nedotknute(): void
    {
        $guest = $this->seatedGuest();
        $pocet = Table::count();

        $this->delete(route('admin.guests.destroy', $guest));

        $this->assertSame($pocet, Table::count());
    }

    // --- zoznam a detail --------------------------------------------------

    public function test_zoznam_a_detail_rezervacie_sa_zobrazia(): void
    {
        $registration = $this->reservation();

        $this->get(route('admin.registrations.index'))->assertOk();
        $this->get(route('admin.registrations.show', $registration->id))->assertOk();
    }

    public function test_vyhladavanie_v_zozname_filtruje(): void
    {
        $this->reservation([['name' => 'Jana Nováková']]);
        $this->reservation([['name' => 'Peter Malý']]);

        $this->get(route('admin.registrations.index', ['search' => 'Peter']))
            ->assertOk()
            ->assertSee('Peter');
    }
}

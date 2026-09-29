<?php

namespace Tests\Feature;

use App\Mail\ReservationCancelled;
use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Poistky v administrácii: platba, vstup, storno, časy a IP adresy. */
class AdminSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->hall();
        $this->actingAs($this->admin());
    }

    // --- platba a vstup ----------------------------------------------------

    public function test_nezaplatenemu_sa_listok_nevyda(): void
    {
        $guest = $this->reservation([[
            'name' => 'Jana Nováková', 'table_id' => Table::first()->id, 'seat_number' => 1, 'paid' => false,
        ]])->guests->first();

        $this->post(route('admin.guests.issue_ticket', $guest))->assertSessionHas('error');

        $this->assertFalse($guest->fresh()->ticket_issued);
        $this->assertNull($guest->fresh()->ticket_code);
    }

    public function test_checkin_nepusti_nezaplateneho(): void
    {
        $guest = $this->seatedGuest('203', ['paid' => false]);

        $this->post(route('admin.checkin.store'), ['ticket_code' => '203'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'NEMÁ ZAPLATENÉ'))
            ->assertSessionMissing('success_guest');

        $this->assertFalse($guest->fresh()->checked_in);
    }

    public function test_usadzac_nepusti_nezaplateneho(): void
    {
        $guest = $this->seatedGuest('203', ['paid' => false]);

        $this->post(route('admin.seating.check_in'), ['guest_id' => $guest->id])->assertSessionHas('error');

        $this->assertFalse($guest->fresh()->checked_in);

        $host = $this->get(route('admin.seating.lookup', ['ticket_code' => '203']))
            ->viewData('page')['props']['guest'];
        $this->assertFalse($host['paid']);
    }

    public function test_usadzac_ukaze_storno_aj_pri_zapisanom_hostovi(): void
    {
        $this->seatedGuest('203', ['checked_in' => true, 'checked_in_at' => now(), 'cancelled_at' => now()]);

        $host = $this->get(route('admin.seating.lookup', ['ticket_code' => '203']))
            ->viewData('page')['props']['guest'];

        $this->assertTrue($host['cancelled']);
        $this->assertNotNull($host['cancelled_at']);
    }

    // --- storno ------------------------------------------------------------

    public function test_storno_hosta_vo_vnutri_treba_potvrdit(): void
    {
        $guest = $this->seatedGuest('203', ['paid' => false, 'checked_in' => true, 'checked_in_at' => now()]);

        $this->post(route('admin.reminders.cancel', $guest->registration_id))->assertSessionHas('error');
        $this->assertNull($guest->fresh()->cancelled_at);

        $this->post(route('admin.reminders.cancel', $guest->registration_id), ['confirm_checked_in' => true])
            ->assertSessionHas('success');
        $this->assertNotNull($guest->fresh()->cancelled_at);
    }

    public function test_email_o_storne_bez_vyzvy_netvrdi_ze_vyzva_bola(): void
    {
        $registration = $this->reservation([['name' => 'Jana Nováková', 'paid' => false]]);

        $html = (new ReservationCancelled($registration, $registration->guests))->render();

        $this->assertStringNotContainsString('výzve', $html);
        $this->assertStringNotContainsString('pripomienke', $html);
    }

    public function test_email_o_storne_po_poslednej_vyzve(): void
    {
        $registration = $this->reservation([['name' => 'Jana Nováková', 'paid' => false, 'final_notice_sent_at' => now()]]);

        $html = (new ReservationCancelled($registration, $registration->guests))->render();

        $this->assertStringContainsString('opakovanej výzve', $html);
    }

    public function test_bez_reply_to_email_nevyzyva_na_odpoved(): void
    {
        config(['mail.reply_to.address' => null]);
        $registration = $this->reservation([['name' => 'Jana Nováková', 'paid' => false]]);

        $html = (new ReservationCancelled($registration, $registration->guests))->render();

        $this->assertStringNotContainsString('odpovedzte na tento e-mail', $html);
    }

    public function test_pripomienka_na_neplatny_email_sa_neodosle(): void
    {
        $registration = $this->reservation([['name' => 'Jana Nováková', 'paid' => false]], ['registrant_email' => 'jana@gmail']);

        $this->post(route('admin.reminders.send', $registration->id), [
            'deadline' => now()->addDays(7)->format('Y-m-d'),
        ])->assertSessionHas('error');

        Mail::assertNothingQueued();
    }

    public function test_prazdne_pripomienky_vedia_ze_nie_su_hostia(): void
    {
        $props = $this->get(route('admin.reminders.index'))->viewData('page')['props'];

        $this->assertFalse($props['hasGuests']);
    }

    // --- vyhľadávanie a údaje ----------------------------------------------

    public function test_vyhladavanie_podla_emailu_hosta(): void
    {
        $this->reservation([
            ['name' => 'Jana Nováková', 'email' => 'jana@email.sk'],
            ['name' => 'Peter Malý', 'email' => 'peter.maly@firma.sk'],
        ]);

        $mena = collect($this->get(route('admin.registrations.index', ['search' => 'peter.maly']))
            ->viewData('page')['props']['guests']['data'])->pluck('name');

        $this->assertSame(['Peter Malý'], $mena->all());
    }

    public function test_alergeny_sa_ukladaju_zoradene(): void
    {
        $guest = $this->reservation([['name' => 'Jana Nováková', 'allergen_ids' => [5, 2, 5]]])->guests->first();

        $this->assertSame([2, 5], $guest->fresh()->allergen_ids);
        $this->assertSame('2, 5', $guest->fresh()->allergens_display);
    }

    // --- čas a IP ----------------------------------------------------------

    public function test_cas_je_slovensky(): void
    {
        $this->assertSame('Europe/Bratislava', config('app.timezone'));
    }

    public function test_zaznam_cinnosti_uklada_skutocnu_ip_za_cloudflarom(): void
    {
        $this->seatedGuest('203');

        $this->withServerVariables(['REMOTE_ADDR' => '172.70.10.20'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->post(route('admin.checkin.store'), ['ticket_code' => '203']);

        $this->assertSame('203.0.113.7', ActivityLog::where('action', 'guest.checked_in')->value('ip_address'));
    }
}

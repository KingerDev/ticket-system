<?php

namespace Tests\Feature;

use App\Mail\FinalNotice;
use App\Mail\PaymentReminder;
use App\Mail\ReservationCancelled;
use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Pripomienky k nezaplateným rezerváciám, termíny a storná. */
class RemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hall();
        $this->actingAs($this->admin());
        Mail::fake();
    }

    private function nezaplatena(array $prepis = []): \App\Models\Registration
    {
        $table = Table::first();

        return $this->reservation([array_merge([
            'name'        => 'Jana Nováková',
            'email'       => 'jana@email.sk',
            'paid'        => false,
            'table_id'    => $table->id,
            'seat_number' => 3,
        ], $prepis)]);
    }

    // --- odoslanie pripomienky -------------------------------------------

    public function test_pripomienka_odide_a_ulozi_termin(): void
    {
        $registration = $this->nezaplatena();
        $termin = now()->addDays(14)->format('Y-m-d');

        $this->post(route('admin.reminders.send', $registration->id), ['deadline' => $termin])
            ->assertSessionHas('success');

        Mail::assertQueued(PaymentReminder::class, fn ($m) => $m->hasTo('jana@email.sk'));

        $guest = $registration->guests->first()->fresh();
        $this->assertSame($termin, $guest->payment_deadline_at->format('Y-m-d'));
        $this->assertNotNull($guest->reminder_sent_at);
        $this->assertNull($guest->final_notice_sent_at);

        $this->assertDatabaseHas('activity_logs', ['action' => 'guest.reminder_sent']);
    }

    public function test_posledna_vyzva_je_samostatny_email(): void
    {
        $registration = $this->nezaplatena();

        $this->post(route('admin.reminders.final_notice', $registration->id), [
            'deadline' => now()->addDays(3)->format('Y-m-d'),
        ])->assertSessionHas('success');

        Mail::assertQueued(FinalNotice::class);
        Mail::assertNotQueued(PaymentReminder::class);

        $this->assertNotNull($registration->guests->first()->fresh()->final_notice_sent_at);
    }

    public function test_termin_je_povinny_a_musi_byt_v_buducnosti(): void
    {
        $registration = $this->nezaplatena();

        $this->post(route('admin.reminders.send', $registration->id), [])
            ->assertSessionHasErrors('deadline');

        $this->post(route('admin.reminders.send', $registration->id), [
            'deadline' => now()->subDay()->format('Y-m-d'),
        ])->assertSessionHasErrors('deadline');

        Mail::assertNothingQueued();
    }

    public function test_pripomienka_sa_tyka_len_nezaplatenych_hosti(): void
    {
        $table = Table::first();
        $registration = $this->reservation([
            ['name' => 'Jana Nováková', 'email' => 'jana@email.sk', 'paid' => true],
            ['name' => 'Peter Malý', 'paid' => false],
        ]);

        $this->post(route('admin.reminders.send', $registration->id), [
            'deadline' => now()->addDays(7)->format('Y-m-d'),
        ]);

        Mail::assertQueued(PaymentReminder::class, fn ($m) => $m->guests->count() === 1
            && $m->guests->first()->name === 'Peter Malý');

        $this->assertNull($registration->guests->first()->fresh()->payment_deadline_at, 'zaplatenému sa termín nenastavuje');
    }

    public function test_zaplatenej_rezervacii_sa_pripomienka_neposiela(): void
    {
        $registration = $this->nezaplatena(['paid' => true]);

        $this->post(route('admin.reminders.send', $registration->id), [
            'deadline' => now()->addDays(7)->format('Y-m-d'),
        ])->assertSessionHas('error');

        Mail::assertNothingQueued();
    }

    // --- storno -----------------------------------------------------------

    public function test_storno_uvolni_miesto_a_zachova_zaznam(): void
    {
        $registration = $this->nezaplatena();
        $guest = $registration->guests->first();

        $this->post(route('admin.reminders.cancel', $registration->id))->assertSessionHas('success');

        $guest->refresh();
        $this->assertNotNull($guest->cancelled_at);
        $this->assertNull($guest->table_id, 'miesto sa má uvoľniť');
        $this->assertNull($guest->seat_number);
        $this->assertNotNull(Guest::find($guest->id), 'záznam sa nesmie zmazať');

        Mail::assertQueued(ReservationCancelled::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'guest.cancelled']);
    }

    public function test_storno_bez_upozornenia_hosta(): void
    {
        $registration = $this->nezaplatena();

        $this->post(route('admin.reminders.cancel', $registration->id), ['notify' => false]);

        Mail::assertNotQueued(ReservationCancelled::class);
        $this->assertNotNull($registration->guests->first()->fresh()->cancelled_at);
    }

    public function test_storno_sa_netyka_zaplatenych_hosti(): void
    {
        $registration = $this->reservation([
            ['name' => 'Jana Nováková', 'email' => 'jana@email.sk', 'paid' => true, 'seat_number' => 1],
            ['name' => 'Peter Malý', 'paid' => false, 'seat_number' => 2],
        ]);

        $this->post(route('admin.reminders.cancel', $registration->id));

        $jana = $registration->guests->firstWhere('name', 'Jana Nováková')->fresh();
        $peter = $registration->guests->firstWhere('name', 'Peter Malý')->fresh();

        $this->assertNull($jana->cancelled_at, 'zaplatený hosť sa stornovať nesmie');
        $this->assertSame(1, $jana->seat_number);
        $this->assertNotNull($peter->cancelled_at);
    }

    public function test_obnovenie_stornovaneho_hosta(): void
    {
        $registration = $this->nezaplatena();
        $guest = $registration->guests->first();
        $this->post(route('admin.reminders.cancel', $registration->id));

        $this->post(route('admin.guests.restore', $guest->id))->assertSessionHas('success');

        $guest->refresh();
        $this->assertNull($guest->cancelled_at);
        $this->assertNull($guest->payment_deadline_at, 'termín sa má vynulovať');
        $this->assertDatabaseHas('activity_logs', ['action' => 'guest.restored']);
    }

    // --- dôsledky storna --------------------------------------------------

    public function test_stornovany_host_sa_nedostane_dnu(): void
    {
        $guest = $this->seatedGuest('007', ['paid' => false]);
        $this->post(route('admin.reminders.cancel', $guest->registration_id));

        $this->post(route('admin.checkin.store'), ['ticket_code' => '007'])
            ->assertSessionHas('error');

        $this->assertFalse($guest->fresh()->checked_in);

        $this->post(route('admin.seating.check_in'), ['guest_id' => $guest->id])
            ->assertSessionHas('error');
    }

    public function test_stornovanemu_hostovi_sa_nepridelí_miesto_ani_listok(): void
    {
        $guest = $this->seatedGuest('007', ['paid' => false]);
        $registrationId = $guest->registration_id;
        $this->post(route('admin.reminders.cancel', $registrationId));

        $this->post(route('admin.registrations.assign', $registrationId), [
            'guest_id' => $guest->id, 'table_id' => Table::first()->id, 'seat_number' => 5,
        ])->assertSessionHas('error');

        $this->post(route('admin.guests.issue_ticket', $guest->id))->assertSessionHas('error');

        $this->assertNull($guest->fresh()->table_id);
    }

    public function test_stornovany_host_sa_nerata_do_poctov(): void
    {
        $registration = $this->nezaplatena();
        $this->reservation([['name' => 'Peter Malý', 'email' => 'peter@email.sk', 'paid' => true]]);

        $this->post(route('admin.reminders.cancel', $registration->id));

        $stats = $this->get(route('dashboard'))->viewData('page')['props']['stats'];

        $this->assertSame(1, $stats['totalGuests'], 'stornovaný sa do počtu hostí neráta');
        $this->assertSame(1, $stats['paidCount']);
        $this->assertSame(0, $stats['unpaidCount']);
        $this->assertSame(1, $stats['cancelledCount']);
    }

    public function test_stornovany_host_nie_je_v_exporte(): void
    {
        $registration = $this->nezaplatena();
        $this->reservation([['name' => 'Peter Malý', 'email' => 'peter@email.sk', 'paid' => true]]);

        $this->post(route('admin.reminders.cancel', $registration->id));

        $obsah = $this->get(route('admin.export.download', [
            'format' => 'excel', 'sort_by' => 'name',
        ]))->streamedContent();

        // Excel je zip – mená sú v ňom komprimované, treba ho rozbaliť.
        $subor = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';
        file_put_contents($subor, $obsah);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($subor) === true, 'export nie je platný xlsx');

        // FastExcel píše hodnoty priamo do hárku, nie do zdieľaných reťazcov.
        $texty = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nazov = $zip->getNameIndex($i);
            if (str_ends_with($nazov, '.xml')) {
                $texty .= $zip->getFromIndex($i);
            }
        }
        $zip->close();
        unlink($subor);

        $this->assertStringNotContainsString('Jana Nováková', $texty, 'stornovaný nemá byť v exporte');
        $this->assertStringContainsString('Peter Malý', $texty, 'aktívny hosť v exporte chýba');
    }

    // --- prehľadová stránka -----------------------------------------------

    public function test_stranka_triedi_rezervacie_podla_stavu(): void
    {
        // bez pripomienky
        $this->nezaplatena(['name' => 'Bez Pripomienky']);

        // po termíne
        $poTermine = $this->nezaplatena(['name' => 'Po Termine']);
        $poTermine->guests->first()->update([
            'payment_deadline_at' => now()->subDays(2),
            'reminder_sent_at'    => now()->subDays(10),
        ]);

        // čaká
        $caka = $this->nezaplatena(['name' => 'Caka Este']);
        $caka->guests->first()->update([
            'payment_deadline_at' => now()->addDays(5),
            'reminder_sent_at'    => now(),
        ]);

        $props = $this->get(route('admin.reminders.index'))->viewData('page')['props'];
        $stavy = collect($props['awaiting'])->pluck('stav', 'registrant_name');

        $this->assertSame('bez_terminu', $stavy['Bez Pripomienky']);
        $this->assertSame('po_termine', $stavy['Po Termine']);
        $this->assertSame('caka', $stavy['Caka Este']);
    }

    public function test_zaplatene_rezervacie_na_stranke_nie_su(): void
    {
        $this->nezaplatena(['paid' => true]);

        $props = $this->get(route('admin.reminders.index'))->viewData('page')['props'];

        $this->assertCount(0, $props['awaiting']);
    }

    public function test_stornovane_su_vo_vlastnom_zozname(): void
    {
        $registration = $this->nezaplatena();
        $this->post(route('admin.reminders.cancel', $registration->id));

        $props = $this->get(route('admin.reminders.index'))->viewData('page')['props'];

        $this->assertCount(0, $props['awaiting'], 'stornovaná už nečaká na platbu');
        $this->assertCount(1, $props['cancelled']);
    }
}

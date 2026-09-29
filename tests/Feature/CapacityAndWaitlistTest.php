<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmation;
use App\Mail\WaitlistPromoted;
use App\Models\Guest;
use App\Models\Registration;
use App\Models\SeatBlock;
use App\Models\Table;
use App\Support\Capacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Kapacita sály, stoličky rezervované organizátormi a náhradníci. */
class CapacityAndWaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function guests(int $count): array
    {
        $names = ['Jana Nováková', 'Peter Malý', 'Eva Krátka', 'Ján Veľký', 'Mária Dlhá'];

        return array_map(fn ($i) => [
            'name'  => $names[$i % count($names)],
            'email' => $i === 0 ? 'jana@email.sk' : '',
        ], range(0, $count - 1));
    }

    private function register(int $count)
    {
        return $this->post(route('register.store'), ['guests' => $this->guests($count)]);
    }

    /** Sála s jedným stolom a daným počtom stoličiek. */
    private function smallHall(int $seats): Table
    {
        $this->hall(1, 1, $seats);

        return Table::first();
    }

    // --- kapacita ----------------------------------------------------------

    public function test_bez_nastavenej_saly_limit_neplati(): void
    {
        $this->assertNull(Capacity::free());

        $this->register(3)->assertSessionHasNoErrors();

        $this->assertNull(Registration::first()->waitlisted_at);
    }

    public function test_volne_miesta_odratavaju_rezervovane_a_hosti(): void
    {
        $table = $this->smallHall(8);
        SeatBlock::create(['table_id' => $table->id, 'seat_number' => 1, 'label' => 'Učiteľ']);
        SeatBlock::create(['table_id' => $table->id, 'seat_number' => 2]);
        $this->reservation([['name' => 'Jana Nováková'], ['name' => 'Peter Malý']]);
        // Stornovaný ani náhradník miesto nezaberajú.
        $this->reservation([['name' => 'Eva Krátka', 'cancelled_at' => now()]]);
        $this->reservation([['name' => 'Ján Veľký']], ['waitlisted_at' => now()]);

        $this->assertSame(['total' => 8, 'blocked' => 2, 'taken' => 2, 'free' => 4], Capacity::summary());
    }

    public function test_formular_dostane_pocet_volnych_miest(): void
    {
        $this->smallHall(5);

        $props = $this->get(route('register'))->viewData('page')['props'];

        $this->assertSame(5, $props['freeSeats']);
    }

    // --- náhradníci --------------------------------------------------------

    public function test_skupina_ktora_sa_zmesti_je_riadna(): void
    {
        $this->smallHall(2);

        $this->register(2)->assertSessionHas('waitlisted', false);

        $this->assertNull(Registration::first()->waitlisted_at);
        $this->assertSame(0, Capacity::free());
    }

    public function test_skupina_ktora_sa_nezmesti_ide_cela_medzi_nahradnikov(): void
    {
        $table = $this->smallHall(3);
        SeatBlock::create(['table_id' => $table->id, 'seat_number' => 1]);
        $this->register(1); // zostane 1 voľné miesto

        $this->register(2)->assertSessionHas('waitlisted', true);

        $nahradnik = Registration::latest('id')->first();
        $this->assertNotNull($nahradnik->waitlisted_at);
        $this->assertSame(2, $nahradnik->guests()->count(), 'skupina sa nedelí');
        $this->assertSame(1, Capacity::free(), 'náhradníci miesto nezaberajú');
    }

    public function test_nahradnik_dostane_email_bez_vyzvy_na_platbu(): void
    {
        $this->smallHall(1);

        $this->register(2);

        $registration = Registration::first();
        $mail = new RegistrationConfirmation($registration);
        $html = $mail->render();

        $this->assertStringContainsString('náhradník', $mail->envelope()->subject);
        $this->assertStringContainsString('Zatiaľ neplaťte', $html);
        $this->assertStringNotContainsString('v hotovosti', $html);
    }

    public function test_nahradnikom_sa_nepripomina_platba(): void
    {
        $this->hall();
        $this->actingAs($this->admin());
        $this->reservation([['name' => 'Jana Nováková']], ['waitlisted_at' => now()]);

        $props = $this->get(route('admin.reminders.index'))->viewData('page')['props'];

        $this->assertCount(0, $props['awaiting']);
    }

    public function test_nahradnikovi_sa_neprideli_miesto(): void
    {
        $table = $this->smallHall(4);
        $this->actingAs($this->admin());
        $registration = $this->reservation([['name' => 'Jana Nováková']], ['waitlisted_at' => now()]);

        $this->post(route('admin.registrations.assign', $registration->id), [
            'guest_id' => $registration->guests->first()->id, 'table_id' => $table->id, 'seat_number' => 1,
        ])->assertSessionHas('error');

        $this->assertNull($registration->guests->first()->fresh()->table_id);
    }

    public function test_presun_z_nahradnikov_ked_je_miesto(): void
    {
        $this->smallHall(2);
        $this->actingAs($this->admin());
        $registration = $this->reservation([['name' => 'Jana Nováková'], ['name' => 'Peter Malý']], ['waitlisted_at' => now()]);

        $this->post(route('admin.waitlist.promote', $registration->id))->assertSessionHas('success');

        $registration->refresh();
        $this->assertNull($registration->waitlisted_at);
        $this->assertNotNull($registration->promoted_at);
        Mail::assertQueued(WaitlistPromoted::class, fn ($m) => $m->hasTo('jana@email.sk'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'registration.promoted']);
    }

    public function test_presun_z_nahradnikov_bez_miesta_neprejde(): void
    {
        $this->smallHall(1);
        $this->actingAs($this->admin());
        $registration = $this->reservation([['name' => 'Jana Nováková'], ['name' => 'Peter Malý']], ['waitlisted_at' => now()]);

        $this->post(route('admin.waitlist.promote', $registration->id))->assertSessionHas('error');

        $this->assertNotNull($registration->fresh()->waitlisted_at);
        Mail::assertNothingQueued();
    }

    public function test_zoznam_nahradnikov_v_poradi(): void
    {
        $this->smallHall(1);
        $this->actingAs($this->admin());
        $this->reservation([['name' => 'Jana Nováková']], ['waitlisted_at' => now()->subHour()]);
        $this->reservation([['name' => 'Peter Malý']], ['waitlisted_at' => now()]);
        $this->reservation([['name' => 'Eva Krátka']]); // riadna

        $props = $this->get(route('admin.waitlist.index'))->viewData('page')['props'];

        $this->assertSame(['Jana Nováková', 'Peter Malý'], collect($props['waitlist'])->pluck('registrant_name')->all());
    }

    // --- rezervované stoličky ----------------------------------------------

    public function test_admin_zarezervuje_a_uvolni_stolicku(): void
    {
        $table = $this->smallHall(4);
        $this->actingAs($this->admin());

        $this->post(route('admin.seat_blocks.store'), ['table_id' => $table->id, 'seat_number' => 3, 'label' => 'Učiteľ – doc. Novák'])
            ->assertSessionHas('success');

        $block = SeatBlock::first();
        $this->assertSame('Učiteľ – doc. Novák', $block->label);
        $this->assertSame(3, Capacity::free());

        $this->delete(route('admin.seat_blocks.destroy', $block))->assertSessionHas('success');
        $this->assertSame(0, SeatBlock::count());
        $this->assertSame(4, Capacity::free());
    }

    public function test_obsadenu_ani_neexistujucu_stolicku_nemozno_rezervovat(): void
    {
        $table = $this->smallHall(4);
        $this->actingAs($this->admin());
        $this->reservation([['name' => 'Jana Nováková', 'table_id' => $table->id, 'seat_number' => 1]]);

        $this->post(route('admin.seat_blocks.store'), ['table_id' => $table->id, 'seat_number' => 1])->assertSessionHas('error');
        $this->post(route('admin.seat_blocks.store'), ['table_id' => $table->id, 'seat_number' => 9])->assertSessionHas('error');

        $this->assertSame(0, SeatBlock::count());
    }

    public function test_rezervovanu_stolicku_nemozno_pridelit_hostovi(): void
    {
        $table = $this->smallHall(4);
        $this->actingAs($this->admin());
        SeatBlock::create(['table_id' => $table->id, 'seat_number' => 2, 'label' => 'Učiteľ']);
        $registration = $this->reservation([['name' => 'Jana Nováková']]);

        $this->post(route('admin.registrations.assign', $registration->id), [
            'guest_id' => $registration->guests->first()->id, 'table_id' => $table->id, 'seat_number' => 2,
        ])->assertSessionHas('error');

        $this->assertNull($registration->guests->first()->fresh()->seat_number);
    }

    public function test_zmensenie_stola_zrusi_rezervacie_mimo_neho(): void
    {
        $table = $this->smallHall(8);
        $this->actingAs($this->superAdmin());
        SeatBlock::create(['table_id' => $table->id, 'seat_number' => 2]);
        SeatBlock::create(['table_id' => $table->id, 'seat_number' => 8]);

        $this->post(route('admin.hall.update'), ['num_rows' => 1, 'tables_per_row' => [1], 'seats_per_table' => 6]);

        $this->assertSame([2], SeatBlock::pluck('seat_number')->all());
    }

    public function test_mapa_ukaze_rezervovane_stolicky(): void
    {
        $table = $this->smallHall(4);
        $this->actingAs($this->admin());
        SeatBlock::create(['table_id' => $table->id, 'seat_number' => 2, 'label' => 'Učiteľ']);

        $props = $this->get(route('admin.tables.map'))->viewData('page')['props'];

        $this->assertSame('Učiteľ', $props['tables'][0]['seat_blocks'][0]['label']);
        $this->assertSame(3, $props['capacity']['free']);
    }

    // --- plná sála ---------------------------------------------------------

    public function test_v_plnej_sale_nemozno_rezervovat_dalsiu_stolicku(): void
    {
        $table = $this->smallHall(2);
        $this->actingAs($this->admin());
        // Dvaja hostia bez prideleného miesta – stoličky vyzerajú voľné, ale sála je plná.
        $this->reservation([['name' => 'Jana Nováková'], ['name' => 'Peter Malý']]);

        $this->post(route('admin.seat_blocks.store'), ['table_id' => $table->id, 'seat_number' => 1])
            ->assertSessionHas('error');

        $this->assertSame(0, SeatBlock::count());
    }

    public function test_v_plnej_sale_nemozno_obnovit_stornovaneho(): void
    {
        $this->smallHall(1);
        $this->actingAs($this->admin());
        $this->reservation([['name' => 'Jana Nováková']]);
        $stornovany = $this->reservation([['name' => 'Peter Malý', 'cancelled_at' => now()]])->guests->first();

        $this->post(route('admin.guests.restore', $stornovany->id))->assertSessionHas('error');

        $this->assertNotNull($stornovany->fresh()->cancelled_at);
        $this->assertSame(0, Capacity::free());
    }

    public function test_obnovenie_ked_je_miesto_prejde(): void
    {
        $this->smallHall(1);
        $this->actingAs($this->admin());
        $stornovany = $this->reservation([['name' => 'Peter Malý', 'cancelled_at' => now()]])->guests->first();

        $this->post(route('admin.guests.restore', $stornovany->id))->assertSessionHas('success');

        $this->assertNull($stornovany->fresh()->cancelled_at);
    }

    public function test_nahradnika_mozno_zmazat_zo_zoznamu(): void
    {
        $this->smallHall(1);
        $this->actingAs($this->admin());
        $nahradnik = $this->reservation([['name' => 'Jana Nováková'], ['name' => 'Peter Malý']], ['waitlisted_at' => now()]);

        $this->from(route('admin.waitlist.index'))
            ->delete(route('admin.registrations.destroy', $nahradnik->id), ['stay' => true])
            ->assertRedirect(route('admin.waitlist.index'));

        $this->assertNull($nahradnik->fresh());
        $this->assertSame(0, Guest::count());
    }

    public function test_zoznam_registracii_radi_hosti_v_ramci_rezervacie(): void
    {
        $this->actingAs($this->admin());
        $this->reservation([['name' => 'Aa Prvý'], ['name' => 'Ab Druhý']]);
        $this->reservation([['name' => 'Ba Prvý'], ['name' => 'Bb Druhý'], ['name' => 'Bc Tretí']]);

        $mena = collect($this->get(route('admin.registrations.index'))->viewData('page')['props']['guests']['data'])->pluck('name');

        $this->assertSame(['Ba Prvý', 'Bb Druhý', 'Bc Tretí', 'Aa Prvý', 'Ab Druhý'], $mena->all());
    }
}

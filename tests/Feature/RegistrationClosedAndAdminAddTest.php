<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmation;
use App\Models\Guest;
use App\Models\Registration;
use App\Models\SeatBlock;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Uzavretie verejných rezervácií a pridávanie hostí administrátorom. */
class RegistrationClosedAndAdminAddTest extends TestCase
{
    use RefreshDatabase;

    private array $hostia = ['guests' => [['name' => 'Jana Nováková', 'email' => 'jana@email.sk']]];

    public function test_uzavrete_formulare_ukazu_oznam(): void
    {
        config(['ples.registration_closed' => true]);

        foreach (['register', 'register.teachers'] as $routa) {
            $this->get(route($routa))->assertInertia(fn (AssertableInertia $page) => $page->component('Registration/Closed'));
        }
    }

    public function test_uzavrete_rezervacie_neprijmu_ani_priamy_post(): void
    {
        config(['ples.registration_closed' => true]);

        $this->post(route('register.store'), $this->hostia)->assertRedirect(route('register'));
        $this->post(route('register.teachers.store'), $this->hostia)->assertRedirect(route('register'));

        $this->assertSame(0, Registration::count());
    }

    public function test_verejnost_nemoze_pridat_hosta_cez_administraciu(): void
    {
        $this->post(route('admin.registrations.store'), ['name' => 'Jana Nováková'])->assertRedirect(route('login'));
        $this->assertSame(0, Registration::count());
    }

    public function test_admin_prida_hosta_aj_po_uzavreti_bez_emailu(): void
    {
        config(['ples.registration_closed' => true]);
        Mail::fake();
        $this->hall();
        $this->actingAs($this->admin());

        $this->post(route('admin.registrations.store'), ['name' => 'Jana Nováková'])->assertSessionHasNoErrors();

        $registracia = Registration::with('guests')->sole();
        $this->assertSame('PLES-' . str_pad((string) $registracia->id, 4, '0', STR_PAD_LEFT), $registracia->reservation_number);
        $this->assertSame('', $registracia->registrant_email);
        $this->assertNull($registracia->waitlisted_at);
        $this->assertSame('Jana Nováková', $registracia->guests->sole()->name);
        Mail::assertNothingQueued();
    }

    public function test_potvrdenie_vyzaduje_email_a_odide(): void
    {
        Mail::fake();
        $this->hall();
        $this->actingAs($this->admin());

        $this->post(route('admin.registrations.store'), ['name' => 'Jana Nováková', 'send_confirmation' => true])
            ->assertSessionHasErrors('email');
        $this->assertSame(0, Registration::count());

        $this->post(route('admin.registrations.store'), ['name' => 'Jana Nováková', 'email' => 'jana@email.sk', 'send_confirmation' => true])
            ->assertSessionHasNoErrors();
        Mail::assertQueued(RegistrationConfirmation::class, fn ($mail) => $mail->hasTo('jana@email.sk'));
    }

    public function test_admin_prida_hosta_do_existujucej_rezervacie(): void
    {
        $this->hall();
        $this->actingAs($this->admin());
        $rezervacia = $this->reservation();

        $this->post(route('admin.registrations.add_guest', $rezervacia->id), ['name' => 'Peter Nový'])->assertSessionHasNoErrors();

        $this->assertSame(['Jana Nováková', 'Peter Nový'], $rezervacia->guests()->orderBy('id')->pluck('name')->all());
    }

    public function test_plna_sala_odmietne_hosta(): void
    {
        $this->hall(1, 1, 1);
        $this->actingAs($this->admin());
        $rezervacia = $this->reservation();

        $this->post(route('admin.registrations.store'), ['name' => 'Peter Nový'])->assertSessionHasErrors('capacity');
        $this->post(route('admin.registrations.add_guest', $rezervacia->id), ['name' => 'Peter Nový'])->assertSessionHasErrors('capacity');

        $this->assertSame(1, Guest::count());
    }

    public function test_ucitel_len_na_rezervovanu_stolicku(): void
    {
        $this->hall();
        $this->actingAs($this->admin());

        $this->post(route('admin.registrations.store'), ['name' => 'Peter Učiteľ', 'is_teacher' => true])->assertSessionHasErrors('capacity');

        SeatBlock::create(['table_id' => Table::first()->id, 'seat_number' => 1]);

        $this->post(route('admin.registrations.store'), ['name' => 'Peter Učiteľ', 'is_teacher' => true])->assertSessionHasNoErrors();
    }

    public function test_nahradnicka_rezervacia_prijme_hosta_aj_v_plnej_sale(): void
    {
        $this->hall(1, 1, 1);
        $this->actingAs($this->admin());
        $this->reservation();
        $nahradnici = $this->reservation([['name' => 'Eva Čakajúca']], ['waitlisted_at' => now()]);

        $this->post(route('admin.registrations.add_guest', $nahradnici->id), ['name' => 'Peter Nový'])->assertSessionHasNoErrors();

        $this->assertSame(2, $nahradnici->guests()->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Usádzač a zápis hostí pri vstupe. */
class SeatingAndCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hall();
        $this->actingAs($this->admin());
    }

    private function hostFromResponse($response): array
    {
        return $response->viewData('page')['props']['guest'];
    }

    // --- usádzač ----------------------------------------------------------

    public function test_usadzac_najde_hosta_podla_listka(): void
    {
        $this->seatedGuest('007');

        $host = $this->hostFromResponse(
            $this->get(route('admin.seating.lookup', ['ticket_code' => '007']))->assertOk()
        );

        $this->assertSame('Jana Nováková', $host['name']);
        $this->assertSame('A1', $host['table_name']);
        $this->assertSame(4, $host['seat_number']);
    }

    public function test_usadzac_doplni_chybajuce_nuly_v_kode(): void
    {
        $this->seatedGuest('007');

        $host = $this->hostFromResponse($this->get(route('admin.seating.lookup', ['ticket_code' => '7'])));

        $this->assertSame('007', $host['ticket_code']);
    }

    public function test_usadzac_zobrazi_alergeny(): void
    {
        $this->seatedGuest('007', [
            'allergen_ids'  => [1, 7],
            'is_vegan'      => true,
            'allergen_note' => 'silná alergia na orechy',
        ]);

        $host = $this->hostFromResponse($this->get(route('admin.seating.lookup', ['ticket_code' => '007'])));

        // Regresia: predtým sa čítal zrušený stĺpec `allergens` a bolo tu vždy prázdno.
        $this->assertNotEmpty($host['allergens']);
        $this->assertStringContainsString('1, 7', $host['allergens']);
        $this->assertStringContainsString('Vegán', $host['allergens']);
        $this->assertStringContainsString('orechy', $host['allergens']);
    }

    public function test_neznamy_listok_ohlasi_chybu(): void
    {
        $response = $this->get(route('admin.seating.lookup', ['ticket_code' => '999']))->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertNull($props['guest']);
        $this->assertStringContainsString('999', $props['error']);
    }

    public function test_potvrdenie_prichodu_z_usadzaca(): void
    {
        $guest = $this->seatedGuest('007');
        $this->assertFalse($guest->checked_in);

        $this->post(route('admin.seating.check_in'), ['guest_id' => $guest->id])
            ->assertSessionHas('success');

        $guest->refresh();
        $this->assertTrue($guest->checked_in);
        $this->assertNotNull($guest->checked_in_at);

        $this->assertDatabaseHas('activity_logs', ['action' => 'guest.checked_in', 'subject_id' => $guest->id]);
    }

    public function test_druhe_potvrdenie_nezmeni_cas_prichodu(): void
    {
        $guest = $this->seatedGuest('007');

        $this->post(route('admin.seating.check_in'), ['guest_id' => $guest->id]);
        $cas = $guest->fresh()->checked_in_at;

        $this->travel(5)->minutes();

        $this->post(route('admin.seating.check_in'), ['guest_id' => $guest->id])
            ->assertSessionHas('error');

        $this->assertEquals($cas, $guest->fresh()->checked_in_at);
        $this->assertSame(1, ActivityLog::where('action', 'guest.checked_in')->count());
    }

    public function test_potvrdenie_prichodu_neexistujuceho_hosta_neprejde(): void
    {
        $this->post(route('admin.seating.check_in'), ['guest_id' => 9999])
            ->assertSessionHasErrors('guest_id');
    }

    // --- check-in stránka -------------------------------------------------

    public function test_checkin_zapise_hosta_podla_kodu(): void
    {
        $guest = $this->seatedGuest('042');

        $this->post(route('admin.checkin.store'), ['ticket_code' => '042'])
            ->assertSessionHas('success_guest');

        $this->assertTrue($guest->fresh()->checked_in);
    }

    public function test_checkin_odmietne_neznamy_kod(): void
    {
        $this->post(route('admin.checkin.store'), ['ticket_code' => '999'])
            ->assertSessionHas('error');
    }

    public function test_checkin_upozorni_na_uz_zapisaneho(): void
    {
        $guest = $this->seatedGuest('042');

        $this->post(route('admin.checkin.store'), ['ticket_code' => '042']);
        $this->post(route('admin.checkin.store'), ['ticket_code' => '042'])
            ->assertSessionHas('already_checked_in_guest');
    }

    public function test_checkin_vyzaduje_kod(): void
    {
        $this->post(route('admin.checkin.store'), [])->assertSessionHasErrors('ticket_code');
    }
}

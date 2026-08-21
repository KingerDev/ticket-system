<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Auditný záznam – dohľadateľnosť, kto čo urobil. */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $sef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hall();
        $this->sef = $this->superAdmin(['name' => 'Hlavný Organizátor']);
        $this->actingAs($this->sef);
    }

    public function test_uprava_hosta_zapise_rozdiel_hodnot(): void
    {
        $guest = $this->reservation([['name' => 'Jana Nováková', 'allergen_ids' => [1, 7], 'is_vegan' => true]])->guests->first();

        $this->patch(route('admin.guests.update', $guest), [
            'name' => 'Jana Kováčová', 'allergen_ids' => [3], 'is_vegetarian' => true,
        ]);

        $log = ActivityLog::where('action', 'guest.updated')->first();

        $this->assertSame('Hlavný Organizátor', $log->user_name);
        $this->assertSame($this->sef->id, $log->user_id);
        $this->assertSame('Jana Nováková', $log->properties['name']['pred']);
        $this->assertSame('Jana Kováčová', $log->properties['name']['po']);
        $this->assertSame([1, 7], $log->properties['allergen_ids']['pred']);
        $this->assertSame([3], $log->properties['allergen_ids']['po']);
    }

    public function test_do_logu_ide_len_to_co_sa_naozaj_zmenilo(): void
    {
        $guest = $this->reservation([['name' => 'Jana Nováková', 'email' => 'jana@email.sk']])->guests->first();

        $this->patch(route('admin.guests.update', $guest), [
            'name' => 'Jana Nováková', 'email' => 'nova@email.sk',
        ]);

        $log = ActivityLog::where('action', 'guest.updated')->first();

        $this->assertArrayHasKey('email', $log->properties);
        $this->assertArrayNotHasKey('name', $log->properties, 'nezmenené pole nemá byť v logu');
    }

    public function test_kazda_dolezita_akcia_sa_zaznamena(): void
    {
        $table = Table::first();
        $registration = $this->reservation([['name' => 'Jana Nováková'], ['name' => 'Peter Malý']]);
        $guest = $registration->guests->first();

        $this->post(route('admin.registrations.assign', $registration->id), [
            'guest_id' => $guest->id, 'table_id' => $table->id, 'seat_number' => 2,
        ]);
        $this->post(route('admin.guests.toggle_paid', $guest));
        $this->post(route('admin.guests.issue_ticket', $guest));
        $this->patch(route('admin.registrations.update_contact', $registration->id), [
            'registrant_name' => 'Peter Malý', 'registrant_email' => 'peter@email.sk',
        ]);
        $this->delete(route('admin.guests.destroy', $registration->guests->last()));

        $this->assertSame([
            'guest.seat_assigned',
            'guest.paid_toggled',
            'guest.ticket_issued',
            'registration.contact_updated',
            'guest.deleted',
        ], ActivityLog::orderBy('id')->pluck('action')->all());
    }

    public function test_zaznam_prezije_zmazanie_uzivatela(): void
    {
        $anna = $this->admin(['name' => 'Anna Adminová', 'email' => 'anna@ef.umb.sk']);

        $this->actingAs($anna);
        $guest = $this->reservation()->guests->first();
        $this->patch(route('admin.guests.update', $guest), ['name' => 'Jana Kováčová']);

        $this->actingAs($this->sef);
        $this->delete(route('admin.users.destroy', $anna));

        $log = ActivityLog::where('action', 'guest.updated')->first();
        $this->assertNull($log->user_id, 'väzba sa má vynulovať');
        $this->assertSame('Anna Adminová', $log->user_name, 'meno musí zostať dohľadateľné');
        $this->assertSame('anna@ef.umb.sk', $log->user_email);
    }

    public function test_log_sa_da_filtrovat(): void
    {
        $anna = $this->admin(['name' => 'Anna Adminová']);

        ActivityLog::record('guest.updated', 'Upravil hosťa Jana Nováková', null, [], $this->sef);
        ActivityLog::record('guest.deleted', 'Odstránil hosťa Peter Malý', null, [], $anna);

        $pocet = fn (array $filtre) => count(
            $this->get(route('admin.activity_log', $filtre))->viewData('page')['props']['logs']['data']
        );

        $this->assertSame(2, $pocet([]));
        $this->assertSame(1, $pocet(['user_id' => $anna->id]));
        $this->assertSame(1, $pocet(['action' => 'guest.updated']));
        $this->assertSame(1, $pocet(['search' => 'Peter']));
        $this->assertSame(0, $pocet(['search' => 'Neexistujúci']));
    }

    public function test_log_nema_cestu_na_upravu_ani_mazanie(): void
    {
        ActivityLog::record('guest.updated', 'Upravil hosťa', null, [], $this->sef);
        $id = ActivityLog::first()->id;

        $this->delete("/admin/activity-log/{$id}")->assertNotFound();
        $this->patch("/admin/activity-log/{$id}")->assertNotFound();

        $this->assertSame(1, ActivityLog::count());
    }

    public function test_zaznam_obsahuje_ip_adresu_a_cas(): void
    {
        $guest = $this->reservation()->guests->first();
        $this->patch(route('admin.guests.update', $guest), ['name' => 'Jana Kováčová']);

        $log = ActivityLog::first();
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->created_at);
    }
}

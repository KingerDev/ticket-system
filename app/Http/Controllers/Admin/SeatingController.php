<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\Table;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeatingController extends Controller
{
    public function index()
    {
        $tables = Table::with(['guests.registration'])->get();

        return Inertia::render('Admin/Seating', [
            'tables' => $tables,
            'guest' => null,
            'error' => null,
        ]);
    }

    /**
     * Potvrdenie príchodu priamo z usádzača.
     *
     * Obsluha pri vstupe tak nemusí prepínať medzi usádzačom a check-inom –
     * nájde hosťa podľa lístka a rovno ho zapíše.
     */
    public function checkIn(Request $request)
    {
        $request->validate(['guest_id' => 'required|exists:guests,id']);

        $guest = Guest::findOrFail($request->guest_id);

        if ($guest->checked_in) {
            return back()->with(
                'error',
                "Hosť {$guest->name} už bol zapísaný o " . $guest->checked_in_at->format('H:i') . '.'
            );
        }

        $guest->update([
            'checked_in'    => true,
            'checked_in_at' => now(),
        ]);

        ActivityLog::record(
            'guest.checked_in',
            "Zapísal pri vstupe hosťa {$guest->name} (lístok č. {$guest->ticket_code}) cez usádzač",
            $guest,
        );

        return back()->with('success', "Hosť {$guest->name} bol zapísaný pri vstupe.");
    }

    public function lookup(Request $request)
    {
        $request->validate(['ticket_code' => 'required|string']);

        $code = str_pad(trim($request->ticket_code), 3, '0', STR_PAD_LEFT);
        $tables = Table::with(['guests.registration'])->get();

        $guest = Guest::with(['table', 'registration'])
            ->where('ticket_code', $code)
            ->first();

        if (!$guest) {
            return Inertia::render('Admin/Seating', [
                'tables' => $tables,
                'guest' => null,
                'error' => 'Lístok s kódom ' . $code . ' sa nenašiel.',
            ]);
        }

        return Inertia::render('Admin/Seating', [
            'tables' => $tables,
            'guest' => [
                'id'          => $guest->id,
                'name'        => $guest->name,
                'is_teacher'  => $guest->is_teacher,
                // Stĺpec allergens už neexistuje (zrušila ho migrácia
                // restructure_allergens_in_guests_table), správny je accessor.
                'allergens'   => $guest->allergens_display,
                'ticket_code' => $guest->ticket_code,
                'table_id'    => $guest->table_id,
                'seat_number' => $guest->seat_number,
                'table_name'  => $guest->table->name ?? null,
                'checked_in'    => $guest->checked_in,
                'checked_in_at' => $guest->checked_in_at?->format('H:i'),
            ],
            'error' => null,
        ]);
    }
}

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
    /** Koľko posledných príchodov ukazuje zoznam „Práve prišli“. */
    private const RECENT_ARRIVALS = 15;

    public function index()
    {
        return $this->render(null, null);
    }

    /**
     * Mapa aj zoznam príchodov sú closures, aby ich partial reload
     * (polling zoznamu „Práve prišli“) zbytočne nenačítaval.
     */
    private function render(?array $guest, ?string $error)
    {
        return Inertia::render('Admin/Seating', [
            'tables'         => fn () => Table::with(['guests.registration', 'seatBlocks'])->get(),
            'recentArrivals' => fn () => $this->recentArrivals(),
            'guest'          => $guest,
            'error'          => $error,
        ]);
    }

    /**
     * Hostia, ktorých práve pustili dnu pri vstupe – usádzač ich ťukne
     * namiesto toho, aby znova písal číslo lístka.
     */
    private function recentArrivals(): array
    {
        return Guest::with('table')
            ->active()
            ->where('checked_in', true)
            ->whereNotNull('checked_in_at')
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_ARRIVALS)
            ->get()
            ->map(fn (Guest $g) => [
                'id'            => $g->id,
                'name'          => $g->name,
                'ticket_code'   => $g->ticket_code,
                'table_name'    => $g->table->name ?? null,
                'seat_number'   => $g->seat_number,
                'checked_in_at' => $g->checked_in_at->format('H:i'),
            ])
            ->all();
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

        if ($guest->isCancelled()) {
            return back()->with('error', "Rezervácia hosťa {$guest->name} bola stornovaná. Lístok neplatí.");
        }

        if (!$guest->paid) {
            return back()->with('error', "Hosť {$guest->name} nemá zaplatené. Vstup nepovoľte – pošlite ho k organizátorom.");
        }

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

        $guest = Guest::with(['table', 'registration'])
            ->where('ticket_code', $code)
            ->first();

        if (!$guest) {
            return $this->render(null, 'Lístok s kódom ' . $code . ' sa nenašiel.');
        }

        return $this->render([
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
            'cancelled'     => $guest->isCancelled(),
            'cancelled_at'  => $guest->cancelled_at?->format('j. n. Y H:i'),
            'paid'          => $guest->paid,
        ], null);
    }
}

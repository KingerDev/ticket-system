<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckInController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/CheckIn');
    }

    public function store(Request $request)
    {
        $request->validate([
            'ticket_code' => 'required|string',
        ]);

        $code = trim($request->ticket_code);

        // Find guest by ticket code
        $guest = Guest::with(['registration', 'table'])->where('ticket_code', $code)->first();

        if (!$guest) {
            return back()->with('error', 'Neplatný lístok. Kód sa nenašiel v systéme.');
        }

        if ($guest->isCancelled()) {
            return back()->with('error', "Rezervácia hosťa {$guest->name} bola stornovaná. Lístok neplatí.");
        }

        if ($guest->checked_in) {
            return back()
                ->with('error', "Hosť {$guest->name} bol už skontrolovaný o " . $guest->checked_in_at->format('H:i') . "!")
                ->with('already_checked_in_guest', [
                    'name'          => $guest->name,
                    'table'         => $guest->table->name ?? 'Neznámy',
                    'seat'          => $guest->seat_number ?? 'Neznáme',
                    'allergens'     => $guest->allergens_display,
                    'is_teacher'    => $guest->is_teacher,
                    'note'          => $guest->note,
                    'checked_in_at' => $guest->checked_in_at->format('H:i'),
                ]);
        }

        // Check in
        $guest->update([
            'checked_in' => true,
            'checked_in_at' => now(),
        ]);

        ActivityLog::record(
            'guest.checked_in',
            "Zapísal pri vstupe hosťa {$guest->name} (lístok č. {$guest->ticket_code})",
            $guest,
        );

        return back()->with('success_guest', [
            'name'       => $guest->name,
            'table'      => $guest->table->name ?? 'Neznámy',
            'seat'       => $guest->seat_number ?? 'Neznáme',
            'allergens'  => $guest->allergens_display,
            'is_teacher' => $guest->is_teacher,
            'note'       => $guest->note,
        ])->with('success', 'Úspešne naskenované a povolený vstup!');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Registration;
use App\Models\Table;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class RegistrationAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Guest::with(['registration', 'table']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('registration', fn($q2) =>
                      $q2->where('reservation_number', 'like', "%{$search}%")
                         ->orWhere('registrant_name', 'like', "%{$search}%")
                  );
            });
        }

        $guests = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Registrations/Index', [
            'guests'  => $guests,
            'filters' => $request->only('search'),
        ]);
    }

    public function show($id)
    {
        $registration = Registration::with(['guests.table'])->findOrFail($id);
        $tables = Table::with(['guests.registration'])->get();

        return Inertia::render('Admin/Registrations/Show', [
            'registration' => $registration,
            'tables' => $tables,
        ]);
    }

    public function assignSeat(Request $request, $id)
    {
        $request->validate([
            'guest_id' => 'required|exists:guests,id',
            'table_id' => 'required|exists:tables,id',
            'seat_number' => 'required|integer',
        ]);

        $registration = Registration::findOrFail($id);
        $guest = $registration->guests()->findOrFail($request->guest_id);
        
        // Ensure seat is free
        $seatOccupied = Guest::where('table_id', $request->table_id)
            ->where('seat_number', $request->seat_number)
            ->exists();

        if ($seatOccupied) {
            return back()->with('error', 'Toto miesto je už obsadené.');
        }

        $guest->update([
            'table_id' => $request->table_id,
            'seat_number' => $request->seat_number,
        ]);

        return back()->with('success', "Miesto pre {$guest->name} bolo úspešne pridelené.");
    }

    public function togglePaid($id)
    {
        $guest = Guest::findOrFail($id);
        $nowPaid = !$guest->paid;
        $guest->update([
            'paid'    => $nowPaid,
            'paid_at' => $nowPaid ? now() : null,
        ]);

        $message = $nowPaid ? "Platba pre {$guest->name} označená ako zaplatená." : "Platba pre {$guest->name} bola zrušená.";
        return back()->with('success', $message);
    }

    public function issueTicket(Request $request, $id)
    {
        // $id is the guest ID
        $guest = Guest::with('registration', 'table')->findOrFail($id);

        if (!$guest->table_id || !$guest->seat_number) {
            return back()->with('error', 'Nemožno vydať lístok bez prideleného miesta.');
        }

        $guest->update([
            'is_teacher' => (bool) $request->input('is_teacher', false),
        ]);

        if (!$guest->ticket_issued) {
            // Generate a unique 3-digit code
            $code = '';
            do {
                $code = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            } while (Guest::where('ticket_code', $code)->exists());

            $guest->update([
                'ticket_code' => $code,
                'ticket_issued' => true,
            ]);
        }

        return back()
            ->with('success', "Lístok bol úspešne vydaný pre hosťa {$guest->name}.")
            ->with('ticket_issued_code', $guest->ticket_code)
            ->with('ticket_issued_name', $guest->name);
    }

    public function printTicket($id)
    {
        $guest = Guest::with('registration', 'table')->findOrFail($id);
        
        if (!$guest->ticket_issued) {
            abort(403, 'Lístok ešte nebol vydaný.');
        }

        return Inertia::render('Admin/Registrations/Ticket', [
            'guest' => $guest
        ]);
    }
}

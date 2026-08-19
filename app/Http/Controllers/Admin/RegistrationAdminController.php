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
            'allergens' => Guest::allergenOptions(),
        ]);
    }

    /**
     * Úprava údajov hosťa administrátorom.
     *
     * Pravidlá sú zámerne rovnaké ako na verejnom formulári – inak by sa cez
     * administráciu dali uložiť údaje, ktoré by hosť sám zadať nemohol.
     */
    public function updateGuest(Request $request, $id)
    {
        $guest = Guest::findOrFail($id);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255', Guest::FULL_NAME_REGEX],
            'email'          => 'nullable|email|max:255',
            'allergen_ids'   => 'nullable|array',
            'allergen_ids.*' => 'integer|between:1,14',
            'is_vegan'       => 'boolean',
            'is_vegetarian'  => 'boolean',
            'is_teacher'     => 'boolean',
            'allergen_note'  => 'nullable|string|max:1000',
            'note'           => 'nullable|string|max:1000',
        ], [
            'name.required' => 'Zadajte meno a priezvisko hosťa.',
            'name.regex'    => 'Zadajte meno aj priezvisko (napr. Jana Nováková).',
        ]);

        $guest->update([
            'name'          => $validated['name'],
            'email'         => $validated['email'] ?? null,
            'allergen_ids'  => $validated['allergen_ids'] ?? [],
            'is_vegan'      => $validated['is_vegan'] ?? false,
            'is_vegetarian' => $validated['is_vegetarian'] ?? false,
            'is_teacher'    => $validated['is_teacher'] ?? false,
            'allergen_note' => $validated['allergen_note'] ?? null,
            'note'          => $validated['note'] ?? null,
        ]);

        return back()->with('success', "Údaje hosťa {$guest->name} boli uložené.");
    }

    /** Kontaktné údaje rezervácie – adresa, na ktorú chodia potvrdenia. */
    public function updateContact(Request $request, $id)
    {
        $registration = Registration::findOrFail($id);

        $validated = $request->validate([
            'registrant_name'  => ['required', 'string', 'max:255'],
            'registrant_email' => ['required', 'email', 'max:255'],
        ], [
            'registrant_name.required'  => 'Zadajte meno kontaktnej osoby.',
            'registrant_email.required' => 'Zadajte kontaktný e-mail.',
        ]);

        $registration->update($validated);

        return back()->with('success', 'Kontaktné údaje rezervácie boli uložené.');
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

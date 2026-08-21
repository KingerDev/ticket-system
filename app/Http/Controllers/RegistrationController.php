<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationConfirmation;
use App\Models\Guest;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RegistrationController extends Controller
{
    public function create()
    {
        return Inertia::render('Registration/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'guests'                        => 'required|array|min:1',
            // Meno aj priezvisko je povinné pre každého hosťa (aspoň dve slová).
            'guests.*.name'                 => ['required', 'string', 'max:255', Guest::FULL_NAME_REGEX],
            'guests.*.email'                => 'nullable|email|max:255',
            'guests.*.allergen_ids'         => 'nullable|array',
            'guests.*.allergen_ids.*'       => 'integer|between:1,14',
            'guests.*.is_vegan'             => 'nullable|boolean',
            'guests.*.is_vegetarian'        => 'nullable|boolean',
            'guests.*.allergen_note'        => 'nullable|string|max:1000',
            'guests.*.note'                 => 'nullable|string|max:1000',
        ], [
            'guests.*.name.required' => 'Zadajte meno a priezvisko hosťa.',
            'guests.*.name.regex'    => 'Zadajte meno aj priezvisko (napr. Jana Nováková).',
        ]);

        $registration = DB::transaction(function () use ($validated) {
            $firstGuest = $validated['guests'][0];

            // Číslo sa odvádza od ID, nie od počtu záznamov. Pri počte by po
            // zmazaní rezervácie dostal ďalší hosť už obsadené číslo a unikátny
            // index by registráciu odmietol chybou 500.
            $registration = Registration::create([
                'reservation_number' => 'DOCASNE-' . Str::uuid(),
                'registrant_name'    => $firstGuest['name'],
                'registrant_email'   => $firstGuest['email'] ?? 'bez-emailu@ples.sk',
            ]);

            $registration->update([
                'reservation_number' => 'PLES-' . str_pad((string) $registration->id, 4, '0', STR_PAD_LEFT),
            ]);

            foreach ($validated['guests'] as $guestData) {
                $registration->guests()->create([
                    'name'          => $guestData['name'],
                    'email'         => $guestData['email'] ?? null,
                    'allergen_ids'  => $guestData['allergen_ids'] ?? [],
                    'is_vegan'      => $guestData['is_vegan'] ?? false,
                    'is_vegetarian' => $guestData['is_vegetarian'] ?? false,
                    'allergen_note' => $guestData['allergen_note'] ?? null,
                    'note'          => $guestData['note'] ?? null,
                ]);
            }

            return $registration;
        });

        // Zámerne queue() a nie send(): pri synchrónnom odosielaní by výpadok
        // SMTP skončil chybou 500, hoci registrácia je už uložená.
        Mail::to($registration->registrant_email)->queue(new RegistrationConfirmation($registration));

        return redirect()->route('register.success')->with('reservation_number', $registration->reservation_number);
    }

    public function success()
    {
        return Inertia::render('Registration/Success', [
            'reservation_number' => session('reservation_number')
        ]);
    }
}

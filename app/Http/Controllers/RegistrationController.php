<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationConfirmation;
use App\Models\Guest;
use App\Models\Registration;
use App\Rules\DeliverableEmail;
use App\Support\Capacity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegistrationController extends Controller
{
    public function create()
    {
        return Inertia::render('Registration/Create', [
            'noteMax'    => Guest::NOTE_MAX_LENGTH,
            'allergens'  => Guest::allergenOptions(),
            'paymentInfo' => config('ples.payment_info'),
            // null = sála ešte nie je nastavená, limit neplatí.
            'freeSeats'   => Capacity::free(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'guests'                        => 'required|array|min:1',
            // Meno aj priezvisko je povinné pre každého hosťa (aspoň dve slová).
            'guests.*.name'                 => ['required', 'string', 'max:255', Guest::FULL_NAME_REGEX],
            // Na e-mail kontaktnej osoby ide potvrdenie – bez neho by sa o rezervácii nedozvedela.
            'guests.0.email'                => ['required', 'string', 'max:255', new DeliverableEmail],
            'guests.*.email'                => ['nullable', 'string', 'max:255', new DeliverableEmail],
            'guests.*.allergen_ids'         => 'nullable|array',
            'guests.*.allergen_ids.*'       => 'integer|between:1,14',
            'guests.*.is_vegan'             => 'nullable|boolean',
            'guests.*.is_vegetarian'        => 'nullable|boolean',
            'guests.*.allergen_note'        => 'nullable|string|max:' . Guest::NOTE_MAX_LENGTH,
            'guests.*.note'                 => 'nullable|string|max:' . Guest::NOTE_MAX_LENGTH,
        ], [
            'guests.*.name.required' => 'Zadajte meno a priezvisko hosťa.',
            'guests.*.name.regex'    => 'Zadajte meno aj priezvisko (napr. Jana Nováková).',
            'guests.0.email.required' => 'Zadajte e-mail kontaktnej osoby – pošleme naň potvrdenie rezervácie.',
        ]);

        // Vegán a vegetarián sa vylučujú – formulár ponúka len jednu voľbu.
        foreach ($validated['guests'] as $index => $guestData) {
            if (! empty($guestData['is_vegan']) && ! empty($guestData['is_vegetarian'])) {
                throw ValidationException::withMessages([
                    "guests.{$index}.is_vegan" => 'Vyberte buď vegán, alebo vegetarián.',
                ]);
            }
        }

        $registration = DB::transaction(function () use ($validated) {
            $firstGuest = $validated['guests'][0];

            // Ak sa celá skupina nezmestí, ide celá medzi náhradníkov – nedelí sa.
            // Formulár na to upozorní vopred; tu sa to rozhodne s istotou pod zámkom.
            Capacity::lock();
            $free = Capacity::free();
            $waitlisted = $free !== null && count($validated['guests']) > $free;

            // Číslo sa odvádza od ID, nie od počtu záznamov. Pri počte by po
            // zmazaní rezervácie dostal ďalší hosť už obsadené číslo a unikátny
            // index by registráciu odmietol chybou 500.
            $registration = Registration::create([
                'reservation_number' => 'DOCASNE-' . Str::uuid(),
                'registrant_name'    => $firstGuest['name'],
                'registrant_email'   => trim($firstGuest['email']),
                'waitlisted_at'      => $waitlisted ? now() : null,
            ]);

            $registration->update([
                'reservation_number' => 'PLES-' . str_pad((string) $registration->id, 4, '0', STR_PAD_LEFT),
            ]);

            foreach ($validated['guests'] as $guestData) {
                $registration->guests()->create([
                    'name'          => $guestData['name'],
                    'email'         => filled($guestData['email'] ?? null) ? trim($guestData['email']) : null,
                    'allergen_ids'  => Guest::normalizeAllergenIds($guestData['allergen_ids'] ?? []),
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

        // Hosťom s vlastným e-mailom pošleme potvrdenie len o nich samých –
        // alergie a odkazy ostatných hostí im do schránky nepatria.
        $registration->guests
            ->filter(fn (Guest $guest) => $guest->email
                && strcasecmp($guest->email, $registration->registrant_email) !== 0)
            ->unique(fn (Guest $guest) => mb_strtolower($guest->email))
            ->each(fn (Guest $guest) => Mail::to($guest->email)
                ->queue(new RegistrationConfirmation($registration, $guest)));

        return redirect()->route('register.success')
            ->with('reservation_number', $registration->reservation_number)
            ->with('waitlisted', $registration->isWaitlisted());
    }

    public function success()
    {
        return Inertia::render('Registration/Success', [
            'reservation_number' => session('reservation_number'),
            'waitlisted'         => (bool) session('waitlisted'),
            'paymentInfo'        => config('ples.payment_info'),
        ]);
    }
}

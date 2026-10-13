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
        return $this->form(false);
    }

    /**
     * Samostatný formulár pre učiteľov. Všetci hostia z neho sú učitelia,
     * a tak berú najprv z rezervovaných stoličiek, nie z verejných miest.
     */
    public function createTeachers()
    {
        return $this->form(true);
    }

    private function form(bool $forTeachers)
    {
        $capacity = Capacity::summary();

        return Inertia::render('Registration/Create', [
            'noteMax'    => Guest::NOTE_MAX_LENGTH,
            'allergens'  => Guest::allergenOptions(),
            'paymentInfo' => config('ples.payment_info'),
            // null = sála ešte nie je nastavená, limit neplatí.
            'freeSeats'    => $capacity['free'],
            // Len či ešte je rezervované miesto pre vyučujúcich – počet
            // zámerne neposielame, vo formulári sa nemá dať zistiť.
            'teacherSeatsLeft' => $capacity['free'] === null || $capacity['blocked_free'] > 0,
            'contactEmail' => Capacity::contactEmail(),
            'forTeachers'  => $forTeachers,
        ]);
    }

    public function store(Request $request)
    {
        return $this->register($request, false);
    }

    public function storeTeachers(Request $request)
    {
        return $this->register($request, true);
    }

    private function register(Request $request, bool $forTeachers)
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
            'guests.*.is_teacher'           => 'nullable|boolean',
            'guests.*.allergen_note'        => 'nullable|string|max:' . Guest::NOTE_MAX_LENGTH,
            'guests.*.note'                 => 'nullable|string|max:' . Guest::NOTE_MAX_LENGTH,
        ], [
            'guests.*.name.required' => 'Zadajte meno a priezvisko hosťa.',
            'guests.*.name.regex'    => 'Zadajte meno aj priezvisko (napr. Jana Nováková).',
            'guests.0.email.required' => 'Zadajte e-mail kontaktnej osoby.',
        ]);

        // Z formulára pre učiteľov sú učitelia všetci, vo verejnom si to hosť zaškrtne sám.
        foreach ($validated['guests'] as $index => $guestData) {
            $validated['guests'][$index]['is_teacher'] = $forTeachers || ! empty($guestData['is_teacher']);
        }

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
            $count = count($validated['guests']);
            $teachers = count(array_filter($validated['guests'], fn ($g) => $g['is_teacher']));

            // Vyučujúci len na rezervované stoličky. Keď dôjdu, nejdú medzi
            // náhradníkov ani na verejné miesta – majú sa ozvať organizátorom.
            if ($teachers > 0 && ! Capacity::teachersFit($teachers)) {
                throw ValidationException::withMessages(['guests' => self::teachersFullMessage()]);
            }

            $waitlisted = ! Capacity::fits($count - $teachers, $teachers);

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
                    'is_teacher'    => $guestData['is_teacher'],
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

    private static function teachersFullMessage(): string
    {
        $contact = Capacity::contactEmail();

        return 'Miesta pre vyučujúcich sú už obsadené. Kontaktujte nás, prosím'
            . ($contact ? " na {$contact}" : '') . ', dohodneme sa.';
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

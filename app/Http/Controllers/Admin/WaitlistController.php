<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WaitlistPromoted;
use App\Models\ActivityLog;
use App\Models\Registration;
use App\Support\Capacity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/**
 * Náhradníci – registrácie, pre ktoré nebolo dosť miest.
 *
 * Nikto sa nepresúva automaticky: keď sa miesto uvoľní, organizátori
 * vyberú, koho presunú medzi riadne registrácie, a tomu príde e-mail.
 */
class WaitlistController extends Controller
{
    public function index()
    {
        $waitlist = Registration::waitlisted()
            ->with(['guests' => fn ($q) => $q->active()->orderBy('id')])
            ->orderBy('waitlisted_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Registration $r) => [
                'id' => $r->id,
                'reservation_number' => $r->reservation_number,
                'registrant_name' => $r->registrant_name,
                'registrant_email' => $r->registrant_email,
                'waitlisted_at' => $r->waitlisted_at->format('j. n. Y H:i'),
                'guests' => $r->guests->pluck('name'),
                'guest_count' => $r->guests->count(),
            ])
            ->values();

        return Inertia::render('Admin/Waitlist/Index', [
            'waitlist' => $waitlist,
            'capacity' => Capacity::summary(),
        ]);
    }

    public function promote($id)
    {
        $result = DB::transaction(function () use ($id) {
            Capacity::lock();

            $registration = Registration::with('guests')->findOrFail($id);

            if (! $registration->isWaitlisted()) {
                return ['error', "Rezervácia {$registration->reservation_number} nie je medzi náhradníkmi."];
            }

            $active = $registration->guests->whereNull('cancelled_at');
            $count = $active->count();
            $teachers = $active->where('is_teacher', true)->count();
            $free = Capacity::free();

            if (! Capacity::teachersFit($teachers)) {
                return ['error', "Rezervácia {$registration->reservation_number} má učiteľov, na ktorých už nezostali rezervované miesta. Najprv rezervujte ďalšie stoličky na mape."];
            }

            if (! Capacity::fits($count - $teachers, $teachers)) {
                return ['error', sprintf(
                    'Rezervácia %s má %d %s, voľných miest je však len %d. Uvoľnite miesto (napr. zrušte rezerváciu stoličky) a skúste znova.',
                    $registration->reservation_number,
                    $count,
                    $count === 1 ? 'hosťa' : 'hostí',
                    $free,
                )];
            }

            $registration->update(['waitlisted_at' => null, 'promoted_at' => now()]);

            return ['success', $registration];
        });

        if ($result[0] === 'error') {
            return back()->with('error', $result[1]);
        }

        $registration = $result[1];

        Mail::to($registration->registrant_email)->queue(new WaitlistPromoted($registration));

        ActivityLog::record(
            'registration.promoted',
            "Presunul rezerváciu {$registration->reservation_number} z náhradníkov medzi riadne",
            $registration,
        );

        return back()->with('success', "Rezervácia {$registration->reservation_number} je riadna. Kontaktnej osobe sme poslali e-mail, že môže prísť zaplatiť.");
    }

    /**
     * Opačný smer – riadnu rezerváciu organizátori vrátia medzi náhradníkov.
     *
     * Hostia prídu o pridelené miesta a zaradia sa na koniec poradia.
     * Zaplateným, s lístkom ani zapísaným pri vstupe sa to nedá – náhradník
     * neplatí a lístok by ho pustil dnu. E-mail sa zámerne neposiela,
     * organizátori sa s kontaktnou osobou dohodnú sami.
     */
    public function demote($id)
    {
        $registration = Registration::with('guests')->findOrFail($id);

        if ($registration->isWaitlisted()) {
            return back()->with('error', "Rezervácia {$registration->reservation_number} už je medzi náhradníkmi.");
        }

        $active = $registration->guests->whereNull('cancelled_at');
        $blocking = $active->filter(fn ($g) => $g->paid || $g->ticket_issued || $g->checked_in);

        if ($blocking->isNotEmpty()) {
            return back()->with('error', sprintf(
                'Rezerváciu %s nemožno presunúť medzi náhradníkov – zaplatené, vydaný lístok alebo zapísaný pri vstupe: %s. Najprv zrušte označenie platby.',
                $registration->reservation_number,
                $blocking->pluck('name')->join(', '),
            ));
        }

        DB::transaction(function () use ($registration, $active) {
            $registration->update(['waitlisted_at' => now(), 'promoted_at' => null]);

            // Termín platby a pripomienky platia len pre riadnych – po
            // prípadnom ďalšom presune medzi riadne sa začne odznova.
            $active->each->update([
                'table_id' => null,
                'seat_number' => null,
                'payment_deadline_at' => null,
                'reminder_sent_at' => null,
                'final_notice_sent_at' => null,
            ]);
        });

        ActivityLog::record(
            'registration.demoted',
            "Presunul rezerváciu {$registration->reservation_number} medzi náhradníkov",
            $registration,
        );

        return back()->with('success', "Rezervácia {$registration->reservation_number} je medzi náhradníkmi. Kontaktnej osobe sa e-mail neposielal.");
    }
}

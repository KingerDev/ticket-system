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
                'id'                 => $r->id,
                'reservation_number' => $r->reservation_number,
                'registrant_name'    => $r->registrant_name,
                'registrant_email'   => $r->registrant_email,
                'waitlisted_at'      => $r->waitlisted_at->format('j. n. Y H:i'),
                'guests'             => $r->guests->pluck('name'),
                'guest_count'        => $r->guests->count(),
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

            $count = $registration->guests->whereNull('cancelled_at')->count();
            $free = Capacity::free();

            if ($free !== null && $count > $free) {
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
}

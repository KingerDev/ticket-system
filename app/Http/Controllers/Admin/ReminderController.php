<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\FinalNotice;
use App\Mail\PaymentReminder;
use App\Mail\ReservationCancelled;
use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/**
 * Pripomienky k nezaplateným rezerváciám a ich storno.
 *
 * Aplikácia nič neposiela ani neruší sama – vždy to spúšťa organizátor.
 * Jej úlohou je len ukázať, komu treba napísať a komu vypršal termín.
 */
class ReminderController extends Controller
{
    public function index()
    {
        $awaiting = Registration::with(['guests' => fn ($q) => $q->orderBy('id')])
            ->whereHas('guests', fn ($q) => $q->awaitingPayment())
            ->get()
            ->map(fn (Registration $r) => $this->prehlad($r, $r->guests->whereNull('cancelled_at')->where('paid', false)))
            ->sortBy(fn ($r) => $r['deadline_at'] ?? '9999')
            ->values();

        $cancelled = Registration::with(['guests' => fn ($q) => $q->cancelled()])
            ->whereHas('guests', fn ($q) => $q->cancelled())
            ->get()
            ->map(fn (Registration $r) => [
                'id'                 => $r->id,
                'reservation_number' => $r->reservation_number,
                'registrant_name'    => $r->registrant_name,
                'guests'             => $r->guests->map(fn (Guest $g) => [
                    'id'           => $g->id,
                    'name'         => $g->name,
                    'cancelled_at' => $g->cancelled_at?->format('j. n. Y H:i'),
                ])->values(),
            ])
            ->values();

        return Inertia::render('Admin/Reminders/Index', [
            'awaiting'  => $awaiting,
            'cancelled' => $cancelled,
            // Predvyplní sa posledný použitý termín, nech sa nemusí písať stále dokola.
            'defaultDeadline' => $this->predvolenyTermin(),
        ]);
    }

    public function sendReminder(Request $request, $id)
    {
        return $this->odosli($request, $id, finalNotice: false);
    }

    public function sendFinalNotice(Request $request, $id)
    {
        return $this->odosli($request, $id, finalNotice: true);
    }

    private function odosli(Request $request, $id, bool $finalNotice)
    {
        $validated = $request->validate([
            'deadline' => ['required', 'date', 'after:today'],
        ], [
            'deadline.required' => 'Zadajte termín, do ktorého má byť rezervácia dokončená.',
            'deadline.after'    => 'Termín musí byť v budúcnosti.',
        ]);

        $registration = Registration::with('guests')->findOrFail($id);
        $guests = $registration->guests->whereNull('cancelled_at')->where('paid', false);

        if ($guests->isEmpty()) {
            return back()->with('error', "Rezervácia {$registration->reservation_number} nemá nezaplatených hostí.");
        }

        $deadline = Carbon::parse($validated['deadline'])->endOfDay();
        $stlpec = $finalNotice ? 'final_notice_sent_at' : 'reminder_sent_at';

        DB::transaction(function () use ($guests, $deadline, $stlpec) {
            foreach ($guests as $guest) {
                $guest->update([
                    'payment_deadline_at' => $deadline,
                    $stlpec               => now(),
                ]);
            }
        });

        $mailable = $finalNotice
            ? new FinalNotice($registration, $guests, $deadline)
            : new PaymentReminder($registration, $guests, $deadline);

        Mail::to($registration->registrant_email)->queue($mailable);

        ActivityLog::record(
            $finalNotice ? 'guest.final_notice_sent' : 'guest.reminder_sent',
            sprintf(
                '%s pre rezerváciu %s (%d %s, termín %s)',
                $finalNotice ? 'Odoslal poslednú výzvu' : 'Odoslal pripomienku',
                $registration->reservation_number,
                $guests->count(),
                $guests->count() === 1 ? 'hosť' : 'hostia',
                $deadline->format('j. n. Y'),
            ),
            $registration,
            ['termín' => $deadline->format('j. n. Y'), 'adresa' => $registration->registrant_email],
        );

        $co = $finalNotice ? 'Posledná výzva' : 'Pripomienka';

        return back()->with('success', "{$co} pre {$registration->reservation_number} bola odoslaná na {$registration->registrant_email}.");
    }

    /** Storno nezaplatených hostí rezervácie. Miesta sa uvoľnia, záznam zostáva. */
    public function cancel(Request $request, $id)
    {
        $registration = Registration::with('guests')->findOrFail($id);
        $guests = $registration->guests->whereNull('cancelled_at')->where('paid', false);

        if ($guests->isEmpty()) {
            return back()->with('error', "Rezervácia {$registration->reservation_number} nemá čo stornovať.");
        }

        DB::transaction(function () use ($guests) {
            foreach ($guests as $guest) {
                $guest->update([
                    'cancelled_at'  => now(),
                    // Miesto sa musí uvoľniť hneď, inak by blokovalo ďalších.
                    'table_id'      => null,
                    'seat_number'   => null,
                    'ticket_issued' => false,
                ]);
            }
        });

        if ($request->boolean('notify', true)) {
            Mail::to($registration->registrant_email)->queue(new ReservationCancelled($registration, $guests));
        }

        ActivityLog::record(
            'guest.cancelled',
            sprintf(
                'Stornoval %d %s v rezervácii %s',
                $guests->count(),
                $guests->count() === 1 ? 'hosťa' : 'hostí',
                $registration->reservation_number,
            ),
            $registration,
            ['hostia' => $guests->pluck('name')->all()],
        );

        return back()->with('success', "Rezervácia {$registration->reservation_number} bola stornovaná, miesta sú voľné.");
    }

    /** Obnovenie stornovaného hosťa – miesto treba prideliť znova. */
    public function restore($id)
    {
        $guest = Guest::with('registration')->findOrFail($id);

        if (! $guest->isCancelled()) {
            return back()->with('error', "Hosť {$guest->name} nie je stornovaný.");
        }

        $guest->update([
            'cancelled_at'        => null,
            'payment_deadline_at' => null,
            'reminder_sent_at'    => null,
            'final_notice_sent_at' => null,
        ]);

        ActivityLog::record(
            'guest.restored',
            "Obnovil stornovaného hosťa {$guest->name} (rezervácia {$guest->registration?->reservation_number})",
            $guest,
        );

        return back()->with('success', "Hosť {$guest->name} bol obnovený. Prideľte mu miesto nanovo.");
    }

    /** @param  \Illuminate\Support\Collection<int, Guest>  $guests */
    private function prehlad(Registration $registration, $guests): array
    {
        $deadline = $guests->max('payment_deadline_at');

        return [
            'id'                 => $registration->id,
            'reservation_number' => $registration->reservation_number,
            'registrant_name'    => $registration->registrant_name,
            'registrant_email'   => $registration->registrant_email,
            'guests'             => $guests->map(fn (Guest $g) => ['id' => $g->id, 'name' => $g->name])->values(),
            'unpaid_count'       => $guests->count(),
            'deadline_at'        => $deadline?->format('Y-m-d'),
            'deadline_label'     => $deadline?->format('j. n. Y'),
            'days_left'          => $deadline ? (int) round(now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false)) : null,
            'reminder_sent_at'   => $guests->max('reminder_sent_at')?->format('j. n. Y'),
            'final_notice_sent_at' => $guests->max('final_notice_sent_at')?->format('j. n. Y'),
            'stav'               => $this->stav($guests, $deadline),
        ];
    }

    private function stav($guests, ?Carbon $deadline): string
    {
        if ($deadline === null) {
            return 'bez_terminu';
        }

        if ($deadline->isPast()) {
            return 'po_termine';
        }

        return $guests->max('final_notice_sent_at') !== null ? 'posledna_vyzva' : 'caka';
    }

    private function predvolenyTermin(): string
    {
        $posledny = Guest::whereNotNull('payment_deadline_at')
            ->orderByDesc('payment_deadline_at')
            ->value('payment_deadline_at');

        return $posledny && $posledny->isFuture()
            ? $posledny->format('Y-m-d')
            : now()->addDays(14)->format('Y-m-d');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Registration;
use App\Models\Table;
use App\Support\Capacity;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalRegistrations = Registration::count();
        // Stornovaní ani náhradníci sa do prehľadu nerátajú – (zatiaľ) neprídu a nejedia.
        $totalGuests = Guest::confirmed()->count();
        $ticketsIssued = Guest::confirmed()->where('ticket_issued', true)->count();
        $guestsCheckedIn = Guest::confirmed()->where('checked_in', true)->count();
        $guestsWithSeats = Guest::confirmed()->whereNotNull('table_id')->count();
        $teachersCount = Guest::confirmed()->where('is_teacher', true)->count();
        $studentsCount = Guest::confirmed()->where('is_teacher', false)->count();
        $paidCount = Guest::confirmed()->where('paid', true)->count();
        $unpaidCount = Guest::confirmed()->where('paid', false)->count();
        $capacity = Capacity::summary();
        $waitlistedGuests = Guest::active()->whereHas('registration', fn ($q) => $q->waitlisted())->count();
        $overdueCount = Guest::overdue()->count();
        $cancelledCount = Guest::cancelled()->count();
        $totalCapacity = (int) Table::sum('capacity');

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalRegistrations' => $totalRegistrations,
                'totalGuests' => $totalGuests,
                'ticketsIssued' => $ticketsIssued,
                'guestsCheckedIn' => $guestsCheckedIn,
                'guestsWithSeats' => $guestsWithSeats,
                'teachersCount' => $teachersCount,
                'studentsCount' => $studentsCount,
                'paidCount' => $paidCount,
                'unpaidCount' => $unpaidCount,
                'totalCapacity' => $totalCapacity,
                'overdueCount' => $overdueCount,
                'cancelledCount' => $cancelledCount,
                'blockedSeats' => $capacity['blocked'],
                'freeSeats' => $capacity['free'],
                'waitlistedGuests' => $waitlistedGuests,
            ]
        ]);
    }
}

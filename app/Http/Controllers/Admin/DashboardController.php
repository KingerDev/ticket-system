<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Registration;
use App\Models\Table;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalRegistrations = Registration::count();
        // Stornovaní hostia sa do prehľadu nerátajú – neprídu a nejedia.
        $totalGuests = Guest::active()->count();
        $ticketsIssued = Guest::active()->where('ticket_issued', true)->count();
        $guestsCheckedIn = Guest::active()->where('checked_in', true)->count();
        $guestsWithSeats = Guest::active()->whereNotNull('table_id')->count();
        $teachersCount = Guest::active()->where('is_teacher', true)->count();
        $studentsCount = Guest::active()->where('is_teacher', false)->count();
        $paidCount = Guest::active()->where('paid', true)->count();
        $unpaidCount = Guest::active()->where('paid', false)->count();
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
            ]
        ]);
    }
}

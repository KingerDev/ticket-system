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
        $totalGuests = Guest::count();
        $ticketsIssued = Guest::where('ticket_issued', true)->count();
        $guestsCheckedIn = Guest::where('checked_in', true)->count();
        $guestsWithSeats = Guest::whereNotNull('table_id')->count();
        $teachersCount = Guest::where('is_teacher', true)->count();
        $studentsCount = Guest::where('is_teacher', false)->count();
        $paidCount = Guest::where('paid', true)->count();
        $unpaidCount = Guest::where('paid', false)->count();
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
            ]
        ]);
    }
}

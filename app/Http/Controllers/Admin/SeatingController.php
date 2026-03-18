<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Table;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeatingController extends Controller
{
    public function index()
    {
        $tables = Table::with(['guests.registration'])->get();

        return Inertia::render('Admin/Seating', [
            'tables' => $tables,
            'guest' => null,
            'error' => null,
        ]);
    }

    public function lookup(Request $request)
    {
        $request->validate(['ticket_code' => 'required|string']);

        $code = str_pad(trim($request->ticket_code), 3, '0', STR_PAD_LEFT);
        $tables = Table::with(['guests.registration'])->get();

        $guest = Guest::with(['table', 'registration'])
            ->where('ticket_code', $code)
            ->first();

        if (!$guest) {
            return Inertia::render('Admin/Seating', [
                'tables' => $tables,
                'guest' => null,
                'error' => 'Lístok s kódom ' . $code . ' sa nenašiel.',
            ]);
        }

        return Inertia::render('Admin/Seating', [
            'tables' => $tables,
            'guest' => [
                'id'          => $guest->id,
                'name'        => $guest->name,
                'is_teacher'  => $guest->is_teacher,
                'allergens'   => $guest->allergens,
                'ticket_code' => $guest->ticket_code,
                'table_id'    => $guest->table_id,
                'seat_number' => $guest->seat_number,
                'table_name'  => $guest->table->name ?? null,
            ],
            'error' => null,
        ]);
    }
}

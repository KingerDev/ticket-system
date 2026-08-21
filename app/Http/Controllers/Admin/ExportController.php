<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;

class ExportController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Export');
    }

    public function export(Request $request)
    {
        $request->validate([
            'sort_by'           => 'required|in:name,table',
            'format'            => 'required|in:pdf,excel',
            'separate_teachers' => 'nullable|boolean',
            'include_allergens' => 'nullable|boolean',
            'include_seat'      => 'nullable|boolean',
            'include_ticket'    => 'nullable|boolean',
        ]);

        // Stornovaní hostia do zoznamov pre kuchyňu ani vstup nepatria.
        $guests = Guest::active()->with(['table', 'registration'])->get();

        // Sort
        if ($request->sort_by === 'name') {
            $guests = $guests->sortBy('name')->values();
        } else {
            $guests = $guests->sortBy(function ($guest) {
                if (!$guest->table) {
                    return 'ZZZ_999_999';
                }
                return $guest->table->row_label
                    . '_' . str_pad($guest->table->position_in_row, 3, '0', STR_PAD_LEFT)
                    . '_' . str_pad($guest->seat_number, 3, '0', STR_PAD_LEFT);
            })->values();
        }

        $separateTeachers  = (bool) $request->separate_teachers;
        $includeAllergens  = (bool) $request->include_allergens;
        $includeSeat       = (bool) $request->include_seat;
        $includeTicket     = (bool) $request->include_ticket;

        $mainList    = $separateTeachers ? $guests->where('is_teacher', false)->values() : $guests;
        $teacherList = $separateTeachers ? $guests->where('is_teacher', true)->values()  : collect();

        $filename = 'hostia_' . now()->format('Ymd_His');

        if ($request->format === 'pdf') {
            return $this->exportPdf($mainList, $teacherList, $includeAllergens, $includeSeat, $includeTicket, $filename);
        }

        return $this->exportExcel($mainList, $teacherList, $includeAllergens, $includeSeat, $includeTicket, $filename);
    }

    private function buildRow(Guest $guest, bool $includeAllergens, bool $includeSeat, bool $includeTicket): array
    {
        $row = ['Meno' => $guest->name];

        if ($includeSeat) {
            $row['Stôl']   = $guest->table->name ?? '-';
            $row['Miesto'] = $guest->seat_number ?? '-';
        }

        if ($includeAllergens) {
            $row['Alergény'] = $guest->allergens_display ?: '-';
        }

        if ($includeTicket) {
            $row['Č. lístka'] = $guest->ticket_code ?: '-';
        }

        $row['Rezervácia'] = $guest->registration->reservation_number ?? '-';

        return $row;
    }

    private function exportExcel($mainList, $teacherList, bool $includeAllergens, bool $includeSeat, bool $includeTicket, string $filename)
    {
        $mainRows    = $mainList->map(fn($g)    => $this->buildRow($g, $includeAllergens, $includeSeat, $includeTicket));
        $teacherRows = $teacherList->map(fn($g) => $this->buildRow($g, $includeAllergens, $includeSeat, $includeTicket));

        if ($teacherList->isNotEmpty()) {
            $sheets = new SheetCollection([
                'Hostia'    => $mainRows,
                'Učitelia'  => $teacherRows,
            ]);
            return (new FastExcel($sheets))->download("{$filename}.xlsx");
        }

        return (new FastExcel($mainRows))->download("{$filename}.xlsx");
    }

    private function exportPdf($mainList, $teacherList, bool $includeAllergens, bool $includeSeat, bool $includeTicket, string $filename)
    {
        $pdf = Pdf::loadView('exports.guests', [
            'mainList'         => $mainList,
            'teacherList'      => $teacherList,
            'includeAllergens' => $includeAllergens,
            'includeSeat'      => $includeSeat,
            'includeTicket'    => $includeTicket,
            'generatedAt'      => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("{$filename}.pdf");
    }
}

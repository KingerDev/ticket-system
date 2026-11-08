<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;

class ExportController extends Controller
{
    private const WORD_COLOR = '1E3A5F';

    public function index()
    {
        return Inertia::render('Admin/Export');
    }

    public function export(Request $request)
    {
        // Admin bez usádzania nesmie zoznam ani zoradiť podľa stolov.
        $showSeat = $request->user()->canSeeSeating();

        $request->validate([
            'sort_by'           => ['required', $showSeat ? 'in:name,ticket,table' : 'in:name,ticket'],
            'format'            => 'required|in:pdf,excel,word',
            'separate_teachers' => 'nullable|boolean',
            'include_allergens' => 'nullable|boolean',
            'include_seat'      => 'nullable|boolean',
            'include_ticket'    => 'nullable|boolean',
        ]);

        // Stornovaní ani náhradníci do zoznamov pre kuchyňu ani vstup nepatria.
        $guests = Guest::confirmed()->with(['table', 'registration'])->get();

        // Sort
        if ($request->sort_by === 'name') {
            $guests = $guests->sortBy('name')->values();
        } elseif ($request->sort_by === 'ticket') {
            // Podľa čísla lístka; hostia bez lístka idú na koniec.
            $guests = $guests->sortBy(fn (Guest $guest) => [
                blank($guest->ticket_code) ? 1 : 0,
                (int) $guest->ticket_code,
                $guest->name,
            ])->values();
        } else {
            // Podľa čísla stola, nie radu a pozície: stoly sú číslované hadíkom,
            // takže poradie v rade nezodpovedá číslam. Bez miesta idú na koniec.
            $guests = $guests->sortBy(fn (Guest $guest) => [
                $guest->table ? 0 : 1,
                (int) $guest->table?->name,
                (int) $guest->seat_number,
                $guest->name,
            ])->values();
        }

        $separateTeachers  = (bool) $request->separate_teachers;
        $includeAllergens  = (bool) $request->include_allergens;
        // Zoznam zoradený podľa stolov bez čísla stola by nemal zmysel.
        $includeSeat       = $showSeat && ($request->boolean('include_seat') || $request->sort_by === 'table');
        // Zoznam zoradený podľa lístkov bez čísla lístka by nemal zmysel.
        $includeTicket     = (bool) $request->include_ticket || $request->sort_by === 'ticket';

        $mainList    = $separateTeachers ? $guests->where('is_teacher', false)->values() : $guests;
        $teacherList = $separateTeachers ? $guests->where('is_teacher', true)->values()  : collect();

        $separateTables = $request->sort_by === 'table';

        $filename = 'hostia_' . now()->format('Ymd_His');

        if ($request->format === 'pdf') {
            return $this->exportPdf($mainList, $teacherList, $includeAllergens, $includeSeat, $includeTicket, $separateTables, $filename);
        }

        if ($request->format === 'word') {
            return $this->exportWord($mainList, $teacherList, $includeAllergens, $includeSeat, $includeTicket, $separateTables, $filename);
        }

        return $this->exportExcel($mainList, $teacherList, $includeAllergens, $includeSeat, $includeTicket, $separateTables, $filename);
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

    /** Riadky pre Excel; pri radení podľa stolov je medzi stolmi prázdny riadok. */
    private function buildRows($list, bool $includeAllergens, bool $includeSeat, bool $includeTicket, bool $separateTables)
    {
        $rows = collect();
        $previous = null;

        foreach ($list as $guest) {
            $row = $this->buildRow($guest, $includeAllergens, $includeSeat, $includeTicket);

            if ($separateTables && $previous && $previous->table_id !== $guest->table_id) {
                $rows->push(array_fill_keys(array_keys($row), ''));
            }

            $rows->push($row);
            $previous = $guest;
        }

        return $rows;
    }

    private function exportExcel($mainList, $teacherList, bool $includeAllergens, bool $includeSeat, bool $includeTicket, bool $separateTables, string $filename)
    {
        $mainRows    = $this->buildRows($mainList, $includeAllergens, $includeSeat, $includeTicket, $separateTables);
        $teacherRows = $this->buildRows($teacherList, $includeAllergens, $includeSeat, $includeTicket, $separateTables);

        if ($teacherList->isNotEmpty()) {
            $sheets = new SheetCollection([
                'Hostia'    => $mainRows,
                'Učitelia'  => $teacherRows,
            ]);
            return (new FastExcel($sheets))->download("{$filename}.xlsx");
        }

        return (new FastExcel($mainRows))->download("{$filename}.xlsx");
    }

    private function exportWord($mainList, $teacherList, bool $includeAllergens, bool $includeSeat, bool $includeTicket, bool $separateTables, string $filename)
    {
        // Bez toho by znak & v mene alebo poznámke pokazil súbor.
        Settings::setOutputEscapingEnabled(true);

        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);

        $section = $word->addSection(['paperSize' => 'A4', 'marginLeft' => 800, 'marginRight' => 800, 'marginTop' => 800, 'marginBottom' => 800]);
        $section->addText('Zoznam hostí', ['bold' => true, 'size' => 18, 'color' => self::WORD_COLOR], ['alignment' => 'center', 'spaceAfter' => 40]);
        $section->addText('Vygenerované: ' . now()->format('d.m.Y H:i'), ['size' => 9, 'color' => '6B7280'], ['alignment' => 'center', 'spaceAfter' => 200]);

        $hasTeachers = $teacherList->isNotEmpty();

        if ($hasTeachers) {
            $this->addWordHeading($section, "Hostia ({$mainList->count()})");
        }

        $this->addWordList($section, $this->buildRows($mainList, $includeAllergens, $includeSeat, $includeTicket, $separateTables));

        if ($hasTeachers) {
            $this->addWordHeading($section, "Učitelia ({$teacherList->count()})");
            $this->addWordList($section, $this->buildRows($teacherList, $includeAllergens, $includeSeat, $includeTicket, $separateTables));
        }

        $total = $mainList->count() + $teacherList->count();
        $section->addText("Celkový počet hostí: {$total}", ['size' => 8, 'color' => '9CA3AF'], ['alignment' => 'right', 'spaceBefore' => 200]);

        return response()->streamDownload(
            fn () => IOFactory::createWriter($word, 'Word2007')->save('php://output'),
            "{$filename}.docx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    private function addWordHeading(Section $section, string $text): void
    {
        $section->addText($text, ['bold' => true, 'size' => 12, 'color' => self::WORD_COLOR], ['spaceBefore' => 200, 'spaceAfter' => 80, 'keepNext' => true]);
    }

    private function addWordList(Section $section, $rows): void
    {
        if ($rows->isEmpty()) {
            $section->addText('Žiadni hostia', ['italic' => true, 'color' => '9CA3AF'], ['alignment' => 'center']);

            return;
        }

        $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'E5E7EB', 'cellMargin' => 50, 'width' => 5000, 'unit' => 'pct']);
        $table->addRow(null, ['tblHeader' => true]);

        foreach (['#', ...array_keys($rows->first())] as $header) {
            $table->addCell(null, ['bgColor' => self::WORD_COLOR])->addText($header, ['bold' => true, 'size' => 9, 'color' => 'FFFFFF']);
        }

        $number = 0;

        foreach ($rows as $row) {
            $isGap = implode('', $row) === '';
            $table->addRow(null, ['cantSplit' => true]);
            $table->addCell()->addText($isGap ? '' : (string) ++$number);

            foreach ($row as $value) {
                $table->addCell()->addText((string) $value);
            }
        }
    }

    private function exportPdf($mainList, $teacherList, bool $includeAllergens, bool $includeSeat, bool $includeTicket, bool $separateTables, string $filename)
    {
        $pdf = Pdf::loadView('exports.guests', [
            'mainList'         => $mainList,
            'teacherList'      => $teacherList,
            'includeAllergens' => $includeAllergens,
            'includeSeat'      => $includeSeat,
            'includeTicket'    => $includeTicket,
            'separateTables'   => $separateTables,
            'generatedAt'      => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("{$filename}.pdf");
    }
}

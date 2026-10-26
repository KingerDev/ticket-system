<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\HallConfig;
use App\Models\SeatBlock;
use App\Models\Table;
use App\Support\Capacity;
use App\Support\TableNumbering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HallConfigController extends Controller
{
    public function edit()
    {
        $config = HallConfig::first();
        $tables = Table::withCount('guests')->get(['id', 'name', 'row_label', 'position_in_row', 'capacity']);

        return Inertia::render('Admin/HallConfig', [
            'config' => $config,
            'tables' => $tables,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'num_rows'          => 'required|integer|min:1|max:26',
            'tables_per_row'    => 'required|array',
            'tables_per_row.*'  => 'integer|min:0',
            'seats_per_table'   => 'required|integer|min:1',
        ]);

        // Stôl je určený radom a pozíciou (napr. „A1“), name je len prepočítané číslo stola.
        $alphabet = range('A', 'Z');
        $newKeys = [];
        for ($r = 0; $r < $validated['num_rows']; $r++) {
            $label = $alphabet[$r];
            $count = $validated['tables_per_row'][$r] ?? 0;
            for ($t = 1; $t <= $count; $t++) {
                $newKeys[] = $label . $t;
            }
        }

        $currentTables = Table::withCount('guests')->get()
            ->keyBy(fn (Table $table) => $table->row_label . $table->position_in_row);
        $currentKeys = $currentTables->keys()->toArray();

        $keysToRemove = array_values(array_diff($currentKeys, $newKeys));
        $keysToAdd    = array_values(array_diff($newKeys, $currentKeys));
        $removedNames = array_values($currentTables->only($keysToRemove)->pluck('name')->all());

        $addedNames = DB::transaction(function () use ($validated, $currentTables, $keysToRemove, $keysToAdd) {
            // Save config
            $config = HallConfig::first();
            if (!$config) {
                HallConfig::create($validated);
            } else {
                $config->update($validated);
            }

            // Remove tables that are no longer in config
            foreach ($keysToRemove as $key) {
                $table = $currentTables[$key];
                // Clear seat_number; table_id is nulled automatically by DB (nullOnDelete)
                Guest::where('table_id', $table->id)->update(['seat_number' => null]);
                $table->delete();
            }

            // Update capacity on tables that remain
            $remainingIds = $currentTables->except($keysToRemove)->pluck('id');
            if ($remainingIds->isNotEmpty()) {
                Table::whereIn('id', $remainingIds)->update(['capacity' => $validated['seats_per_table']]);

                // Rezervované stoličky, ktoré po zmenšení stola už neexistujú.
                SeatBlock::whereIn('table_id', $remainingIds)
                    ->where('seat_number', '>', $validated['seats_per_table'])
                    ->delete();
            }

            // Add new tables
            $addedIds = [];
            foreach ($keysToAdd as $key) {
                $addedIds[] = Table::create([
                    'name'           => $key,
                    'row_label'      => substr($key, 0, 1),
                    'position_in_row'=> (int) substr($key, 1),
                    'capacity'       => $validated['seats_per_table'],
                ])->id;
            }

            TableNumbering::renumber();

            return Table::whereIn('id', $addedIds)->pluck('name')->sort(SORT_NUMERIC)->values()->all();
        });

        ActivityLog::record(
            'hall.updated',
            sprintf(
                'Zmenil rozloženie sály: %d radov, %d miest pri stole (pribudlo %d, ubudlo %d stolov)',
                $validated['num_rows'],
                $validated['seats_per_table'],
                count($addedNames),
                count($removedNames),
            ),
            null,
            ['pridané' => $addedNames, 'odobraté' => $removedNames],
        );

        return redirect()->route('admin.hall.edit')->with('success', 'Konfigurácia sály bola úspešne uložená.');
    }

    public function map(Request $request)
    {
        $config = HallConfig::first();
        $tables = Table::with(['guests.registration', 'seatBlocks'])->get();

        // Admin bez usádzania vidí len, ktoré stoličky sú obsadené a rezervované –
        // ani mená hostí, ani popis rezervácie (býva v ňom meno učiteľa).
        if (! $request->user()->canSeeSeating()) {
            $tables = $tables->map(fn (Table $table) => [
                ...$table->only(['id', 'name', 'row_label', 'position_in_row', 'capacity']),
                'guests'      => $table->guests->map(fn ($g) => ['seat_number' => $g->seat_number])->values(),
                'seat_blocks' => $table->seatBlocks->map(fn ($b) => ['seat_number' => $b->seat_number])->values(),
            ]);
        }

        return Inertia::render('Admin/TableMap', [
            'config'   => $config,
            'tables'   => $tables,
            'capacity' => Capacity::summary(),
        ]);
    }
}

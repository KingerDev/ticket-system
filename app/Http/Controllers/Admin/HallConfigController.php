<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\HallConfig;
use App\Models\Table;
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

        // Build the set of table names the new config produces
        $alphabet = range('A', 'Z');
        $newTableNames = [];
        for ($r = 0; $r < $validated['num_rows']; $r++) {
            $label = $alphabet[$r];
            $count = $validated['tables_per_row'][$r] ?? 0;
            for ($t = 1; $t <= $count; $t++) {
                $newTableNames[] = $label . $t;
            }
        }

        $currentTables = Table::withCount('guests')->get()->keyBy('name');
        $currentNames  = $currentTables->keys()->toArray();

        $namesToRemove = array_values(array_diff($currentNames, $newTableNames));
        $namesToAdd    = array_values(array_diff($newTableNames, $currentNames));

        DB::transaction(function () use ($validated, $currentTables, $namesToRemove, $namesToAdd, $alphabet) {
            // Save config
            $config = HallConfig::first();
            if (!$config) {
                HallConfig::create($validated);
            } else {
                $config->update($validated);
            }

            // Remove tables that are no longer in config
            foreach ($namesToRemove as $name) {
                $table = $currentTables[$name];
                // Clear seat_number; table_id is nulled automatically by DB (nullOnDelete)
                Guest::where('table_id', $table->id)->update(['seat_number' => null]);
                $table->delete();
            }

            // Update capacity on tables that remain
            $remainingIds = $currentTables->except($namesToRemove)->pluck('id');
            if ($remainingIds->isNotEmpty()) {
                Table::whereIn('id', $remainingIds)->update(['capacity' => $validated['seats_per_table']]);
            }

            // Add new tables
            foreach ($namesToAdd as $name) {
                $label = substr($name, 0, 1);
                $pos   = (int) substr($name, 1);
                Table::create([
                    'name'           => $name,
                    'row_label'      => $label,
                    'position_in_row'=> $pos,
                    'capacity'       => $validated['seats_per_table'],
                ]);
            }
        });

        return redirect()->route('admin.hall.edit')->with('success', 'Konfigurácia sály bola úspešne uložená.');
    }

    public function map()
    {
        $config = HallConfig::first();
        $tables = Table::with(['guests.registration'])->get();

        return Inertia::render('Admin/TableMap', [
            'config' => $config,
            'tables' => $tables,
        ]);
    }
}

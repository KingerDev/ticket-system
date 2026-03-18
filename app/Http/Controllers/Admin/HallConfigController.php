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
        
        // Ensure accurate locked status
        $isLocked = Guest::where('ticket_issued', true)->exists();
        if ($config && $config->locked !== $isLocked) {
            $config->update(['locked' => $isLocked]);
        }

        return Inertia::render('Admin/HallConfig', [
            'config' => $config,
        ]);
    }

    public function update(Request $request)
    {
        $config = HallConfig::first();
        if ($config && $config->locked) {
            return back()->with('error', 'Konfigurácia sály je zamknutá, pretože už boli vydané lístky.');
        }

        $validated = $request->validate([
            'num_rows' => 'required|integer|min:1|max:26',
            'tables_per_row' => 'required|array',
            'tables_per_row.*' => 'integer|min:1',
            'seats_per_table' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($validated, $config) {
            if (!$config) {
                $config = HallConfig::create($validated);
            } else {
                $config->update($validated);
            }

            // Recreate tables
            Table::query()->delete();

            $alphabet = range('A', 'Z');
            
            for ($r = 0; $r < $validated['num_rows']; $r++) {
                $rowLabel = $alphabet[$r];
                $tableCount = $validated['tables_per_row'][$r] ?? 0;

                for ($t = 1; $t <= $tableCount; $t++) {
                    Table::create([
                        'name' => $rowLabel . $t,
                        'row_label' => $rowLabel,
                        'position_in_row' => $t,
                        'capacity' => $validated['seats_per_table'],
                    ]);
                }
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

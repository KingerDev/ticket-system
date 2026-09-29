<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\SeatBlock;
use App\Models\Table;
use Illuminate\Http\Request;

/**
 * Stoličky, ktoré si organizátori rezervujú vopred (napr. pre učiteľov).
 *
 * Je to len blok s popisom – nie hosť. Zablokovaná stolička sa nedá prideliť
 * hosťovi a nepočíta sa do voľných miest vo verejnom formulári.
 */
class SeatBlockController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'table_id'    => 'required|exists:tables,id',
            'seat_number' => 'required|integer|min:1',
            'label'       => 'nullable|string|max:255',
        ]);

        $table = Table::findOrFail($validated['table_id']);

        if ($validated['seat_number'] > $table->capacity) {
            return back()->with('error', "Stôl {$table->name} nemá miesto {$validated['seat_number']}.");
        }

        $guest = Guest::where('table_id', $table->id)->where('seat_number', $validated['seat_number'])->first();
        if ($guest) {
            return back()->with('error', "Miesto {$validated['seat_number']} pri stole {$table->name} už má hosť {$guest->name}.");
        }

        if (SeatBlock::where('table_id', $table->id)->where('seat_number', $validated['seat_number'])->exists()) {
            return back()->with('error', "Miesto {$validated['seat_number']} pri stole {$table->name} je už rezervované.");
        }

        $block = SeatBlock::create([
            'table_id'    => $table->id,
            'seat_number' => $validated['seat_number'],
            'label'       => $validated['label'] ?? null,
            'user_id'     => $request->user()?->id,
        ]);

        ActivityLog::record(
            'seat.blocked',
            "Rezervoval miesto {$block->seat_number} pri stole {$table->name}" . ($block->label ? " ({$block->label})" : ''),
            $block,
        );

        return back()->with('success', "Miesto {$block->seat_number} pri stole {$table->name} je rezervované.");
    }

    public function update(Request $request, SeatBlock $seatBlock)
    {
        $validated = $request->validate(['label' => 'nullable|string|max:255']);

        $before = $seatBlock->only(['label']);
        $seatBlock->update(['label' => $validated['label'] ?? null]);

        ActivityLog::record(
            'seat.block_updated',
            "Upravil rezerváciu miesta {$seatBlock->seat_number} pri stole {$seatBlock->table->name}",
            $seatBlock,
            ActivityLog::diff($before, $seatBlock->only(['label'])),
        );

        return back()->with('success', 'Popis rezervovaného miesta bol uložený.');
    }

    public function destroy(SeatBlock $seatBlock)
    {
        $table = $seatBlock->table;
        $seatBlock->delete();

        ActivityLog::record(
            'seat.unblocked',
            "Zrušil rezerváciu miesta {$seatBlock->seat_number} pri stole {$table?->name}" . ($seatBlock->label ? " ({$seatBlock->label})" : ''),
        );

        return back()->with('success', "Miesto {$seatBlock->seat_number} pri stole {$table?->name} je opäť voľné.");
    }
}

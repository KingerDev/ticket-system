<?php

use App\Support\TableNumbering;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Stoly A1, A2, B1… dostanú čísla 1, 2, 3… „hadíkom“ (viď TableNumbering). */
    public function up(): void
    {
        TableNumbering::renumber();
    }

    public function down(): void
    {
        foreach (DB::table('tables')->get() as $table) {
            DB::table('tables')->where('id', $table->id)->update(['name' => $table->row_label . $table->position_in_row]);
        }
    }
};

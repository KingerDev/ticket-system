<?php

namespace App\Support;

use App\Models\Table;

/**
 * Čísla stolov „hadíkom“: rad A sa čísluje sprava doľava (pri pohľade od parketu
 * je stôl 1 vpravo), rad B zľava doprava, rad C znova sprava doľava atď.
 *
 * Stôl sa identifikuje radom a pozíciou v rade (row_label + position_in_row),
 * name je len zobrazované číslo, ktoré sa po každej zmene sály prepočíta.
 */
class TableNumbering
{
    public static function renumber(): void
    {
        $rows = Table::orderBy('row_label')->orderBy('position_in_row')->get()->groupBy('row_label');

        $offset = 0;
        foreach ($rows->values() as $rowIndex => $tables) {
            $count = $tables->count();

            foreach ($tables->values() as $i => $table) {
                $number = $offset + ($rowIndex % 2 === 0 ? $count - $i : $i + 1);

                if ($table->name !== (string) $number) {
                    $table->update(['name' => (string) $number]);
                }
            }

            $offset += $count;
        }
    }
}

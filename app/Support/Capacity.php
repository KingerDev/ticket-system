<?php

namespace App\Support;

use App\Models\Guest;
use App\Models\HallConfig;
use App\Models\SeatBlock;
use App\Models\Table;

/**
 * Koľko miest ešte zostáva pre verejnosť.
 *
 * Voľné = všetky stoličky − zablokované organizátormi − hostia s riadnou
 * registráciou. Stornovaní ani náhradníci miesto nezaberajú.
 */
final class Capacity
{
    /** @return array{total: int, blocked: int, taken: int, free: int|null} */
    public static function summary(): array
    {
        $total = (int) Table::sum('capacity');
        $blocked = SeatBlock::count();
        $taken = Guest::confirmed()->count();

        return [
            'total'   => $total,
            'blocked' => $blocked,
            'taken'   => $taken,
            // Kým nie je nastavená sála, limit sa neuplatňuje.
            'free'    => $total === 0 ? null : max(0, $total - $blocked - $taken),
        ];
    }

    /** Voľné miesta, alebo null, ak sála ešte nie je nastavená (bez limitu). */
    public static function free(): ?int
    {
        return self::summary()['free'];
    }

    /**
     * Zamkne konfiguráciu sály do konca transakcie, aby si dve súbežné
     * registrácie nerozdelili to isté posledné miesto.
     */
    public static function lock(): void
    {
        HallConfig::query()->lockForUpdate()->first();
    }
}

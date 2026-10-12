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
 *
 * Učitelia bez prideleného miesta sa rátajú do stoličiek zablokovaných
 * organizátormi (tie sú pre nich), takže verejnosti miesto neuberajú.
 * Až keď ich je viac než blokov, zvyšok berie z verejných miest. Po usadení
 * na rezervovanú stoličku blok zanikne a učiteľ sa ráta ako bežný hosť.
 */
final class Capacity
{
    /** @return array{total: int, blocked: int, taken: int, teachers_waiting: int, blocked_free: int, free: int|null} */
    public static function summary(): array
    {
        $total = (int) Table::sum('capacity');
        $blocked = SeatBlock::count();
        $taken = Guest::confirmed()->count();
        $teachersWaiting = Guest::confirmed()->where('is_teacher', true)->whereNull('table_id')->count();
        $covered = min($teachersWaiting, $blocked);

        return [
            'total'            => $total,
            'blocked'          => $blocked,
            'taken'            => $taken,
            // Učitelia bez miesta, ktorí čakajú na rezervovanú stoličku.
            'teachers_waiting' => $teachersWaiting,
            // Rezervované stoličky, ktoré ešte nemá „obsadené“ žiadny učiteľ.
            'blocked_free'     => $blocked - $covered,
            // Kým nie je nastavená sála, limit sa neuplatňuje.
            'free'             => $total === 0 ? null : max(0, $total - $blocked - $taken + $covered),
        ];
    }

    /** Voľné miesta, alebo null, ak sála ešte nie je nastavená (bez limitu). */
    public static function free(): ?int
    {
        return self::summary()['free'];
    }

    /**
     * Zmestí sa skupina? Učitelia berú najprv z rezervovaných stoličiek,
     * až potom z verejných miest.
     */
    public static function fits(int $regular, int $teachers = 0): bool
    {
        $summary = self::summary();

        if ($summary['free'] === null) {
            return true;
        }

        $overflow = max(0, $teachers - $summary['blocked_free']);

        return $regular + $overflow <= $summary['free'];
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

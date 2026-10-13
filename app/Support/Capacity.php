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
 * Z verejných miest nikdy neberú – keď rezervované stoličky dôjdu, ďalší
 * učiteľ sa nezaregistruje a má kontaktovať organizátorov. Po usadení na
 * rezervovanú stoličku blok zanikne a učiteľ sa ráta ako bežný hosť.
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
     * Zmestí sa skupina? Učitelia len na rezervované stoličky, ostatní
     * len na verejné miesta.
     */
    public static function fits(int $regular, int $teachers = 0): bool
    {
        $summary = self::summary();

        if ($summary['free'] === null) {
            return true;
        }

        return $teachers <= $summary['blocked_free'] && $regular <= $summary['free'];
    }

    /** Zmestia sa učitelia na rezervované stoličky? */
    public static function teachersFit(int $teachers): bool
    {
        $summary = self::summary();

        return $summary['free'] === null || $teachers <= $summary['blocked_free'];
    }

    /**
     * Prečo hosťa nemožno označiť (alebo odznačiť) ako učiteľa, alebo null.
     *
     * Na kapacitu to vplýva len pri riadnom hosťovi bez miesta: učiteľ potom
     * zaberá rezervovanú stoličku, ostatní verejné miesto.
     */
    public static function teacherChangeError(Guest $guest, bool $isTeacher): ?string
    {
        if ($guest->is_teacher === $isTeacher
            || $guest->isCancelled()
            || $guest->table_id
            || $guest->registration?->isWaitlisted()) {
            return null;
        }

        $summary = self::summary();

        if ($summary['free'] === null) {
            return null;
        }

        if ($isTeacher && $summary['blocked_free'] < 1) {
            return "{$guest->name} sa nedá označiť ako učiteľ – všetky rezervované miesta sú už obsadené. Najprv rezervujte ďalšiu stoličku na mape.";
        }

        if (! $isTeacher && $summary['free'] < 1) {
            return "{$guest->name} sa nedá odznačiť ako učiteľ – pre verejnosť už nie je voľné miesto.";
        }

        return null;
    }

    /** Kontakt na organizátorov pre hlášky „kontaktujte nás“, alebo null. */
    public static function contactEmail(): ?string
    {
        return config('ples.contact_email') ?: config('mail.reply_to.address') ?: null;
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

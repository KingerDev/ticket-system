<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'checked_in'    => 'boolean',
        'checked_in_at' => 'datetime',
        'ticket_issued' => 'boolean',
        'is_teacher'    => 'boolean',
        'allergen_ids'  => 'array',
        'is_vegan'      => 'boolean',
        'is_vegetarian' => 'boolean',
        'paid'          => 'boolean',
        'paid_at'       => 'datetime',
        'payment_deadline_at'  => 'datetime',
        'reminder_sent_at'     => 'datetime',
        'final_notice_sent_at' => 'datetime',
        'cancelled_at'         => 'datetime',
    ];

    /** Hostia, ktorí sa plesu reálne zúčastnia – bez stornovaných. */
    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_at');
    }

    /** Nezaplatení, ktorých rezervácia ešte platí. */
    public function scopeAwaitingPayment($query)
    {
        return $query->active()->where('paid', false);
    }

    /** Termín uplynul a stále nie je zaplatené – kandidáti na storno. */
    public function scopeOverdue($query)
    {
        return $query->awaitingPayment()
            ->whereNotNull('payment_deadline_at')
            ->where('payment_deadline_at', '<', now());
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->paid
            && ! $this->isCancelled()
            && $this->payment_deadline_at !== null
            && $this->payment_deadline_at->isPast();
    }

    /** Koľko dní zostáva do termínu; záporné číslo znamená po termíne. */
    public function daysToDeadline(): ?int
    {
        return $this->payment_deadline_at?->startOfDay()->diffInDays(now()->startOfDay(), false) * -1;
    }

    /**
     * Meno aj priezvisko – aspoň dve slová.
     *
     * Používa ju verejný formulár aj administrácia, aby platili rovnaké
     * pravidlá na oboch stranách.
     */
    public const FULL_NAME_REGEX = "regex:/^\\p{L}[\\p{L}\\p{M}'\\-.]*(\\s+\\p{L}[\\p{L}\\p{M}'\\-.]*)+$/u";

    // EU allergens per Slovak norms
    public const ALLERGENS = [
        1  => 'Obilniny s lepkom',
        2  => 'Kôrovce',
        3  => 'Vajcia',
        4  => 'Ryby',
        5  => 'Arašidy',
        6  => 'Sója',
        7  => 'Mlieko',
        8  => 'Orechy',
        9  => 'Zeler',
        10 => 'Horčica',
        11 => 'Sezamové semená',
        12 => 'Siričitany',
        13 => 'Lupina',
        14 => 'Mäkkýše',
    ];

    // Compact summary for display (numbers + dietary labels + note)
    public function getAllergensDisplayAttribute(): string
    {
        $parts = [];
        if (!empty($this->allergen_ids)) {
            $parts[] = implode(', ', $this->allergen_ids);
        }
        if ($this->is_vegan)       $parts[] = 'Vegán';
        if ($this->is_vegetarian)  $parts[] = 'Vegetarián';
        if ($this->allergen_note)  $parts[] = $this->allergen_note;
        return implode(' | ', $parts);
    }

    /** Zoznam alergénov pre frontend: [{ id, name }, ...]. */
    public static function allergenOptions(): array
    {
        return array_map(
            fn ($id, $name) => ['id' => $id, 'name' => $name],
            array_keys(self::ALLERGENS),
            self::ALLERGENS
        );
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }
}

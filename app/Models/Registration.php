<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Registration extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'waitlisted_at' => 'datetime',
        'promoted_at'   => 'datetime',
    ];

    /**
     * Nová rezervácia s číslom PLES-0001.
     *
     * Číslo sa odvádza od ID, nie od počtu záznamov. Pri počte by po zmazaní
     * rezervácie dostal ďalší hosť už obsadené číslo a unikátny index by
     * registráciu odmietol chybou 500.
     */
    public static function createNumbered(array $attributes): self
    {
        $registration = self::create(['reservation_number' => 'DOCASNE-' . Str::uuid()] + $attributes);

        $registration->update([
            'reservation_number' => 'PLES-' . str_pad((string) $registration->id, 4, '0', STR_PAD_LEFT),
        ]);

        return $registration;
    }

    public function isWaitlisted(): bool
    {
        return $this->waitlisted_at !== null;
    }

    public function scopeWaitlisted($query)
    {
        return $query->whereNotNull('waitlisted_at');
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stolička zablokovaná organizátormi – do verejnej kapacity sa nepočíta. */
class SeatBlock extends Model
{
    protected $guarded = [];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

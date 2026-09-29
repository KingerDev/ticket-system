<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'waitlisted_at' => 'datetime',
        'promoted_at'   => 'datetime',
    ];

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

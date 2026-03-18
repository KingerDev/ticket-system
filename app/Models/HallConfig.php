<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HallConfig extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tables_per_row' => 'array',
        'locked' => 'boolean',
    ];
}

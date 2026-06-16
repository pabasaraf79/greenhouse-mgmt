<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Threshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'greenhouse_id',
        'parameter',
        'warning_min',
        'warning_max',
        'critical_min',
        'critical_max',
        'unit',
    ];

    protected $casts = [
        'warning_min' => 'float',
        'warning_max' => 'float',
        'critical_min' => 'float',
        'critical_max' => 'float',
    ];

    public function greenhouse(): BelongsTo
    {
        return $this->belongsTo(Greenhouse::class);
    }
}

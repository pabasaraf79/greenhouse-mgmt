<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FertigationSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'greenhouse_id',
        'name',
        'days_of_week',
        'start_time',
        'duration_seconds',
        'dose_seconds',
        'enabled',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'duration_seconds' => 'integer',
        'dose_seconds' => 'integer',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function greenhouse(): BelongsTo
    {
        return $this->belongsTo(Greenhouse::class);
    }
}

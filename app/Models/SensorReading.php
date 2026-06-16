<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorReading extends Model
{
    use HasFactory;

    /**
     * Only created_at is managed; recorded_at is set explicitly.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'device_id',
        'temperature',
        'humidity',
        'soil_moisture',
        'water_level_cm',
        'gas_level',
        'rain',
        'motion',
        'raw_payload',
        'recorded_at',
    ];

    protected $casts = [
        'temperature' => 'float',
        'humidity' => 'float',
        'soil_moisture' => 'float',
        'water_level_cm' => 'integer',
        'gas_level' => 'integer',
        'rain' => 'integer',
        'motion' => 'boolean',
        'raw_payload' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}

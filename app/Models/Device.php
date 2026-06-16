<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'greenhouse_id',
        'name',
        'identifier',
        'api_key',
        'ip_address',
        'status',
        'last_seen_at',
        'firmware_version',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function greenhouse(): BelongsTo
    {
        return $this->belongsTo(Greenhouse::class);
    }

    public function sensorReadings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function actuatorCommands(): HasMany
    {
        return $this->hasMany(ActuatorCommand::class);
    }
}

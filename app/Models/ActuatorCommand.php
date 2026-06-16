<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActuatorCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'actuator',
        'command',
        'duration',
        'source',
        'status',
        'issued_by',
        'sent_at',
        'executed_at',
    ];

    protected $casts = [
        'duration' => 'integer',
        'sent_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

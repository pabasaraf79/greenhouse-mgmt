<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Greenhouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'description',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function thresholds(): HasMany
    {
        return $this->hasMany(Threshold::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function fertigationSchedules(): HasMany
    {
        return $this->hasMany(FertigationSchedule::class);
    }
}

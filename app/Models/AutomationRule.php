<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    protected $fillable = [
        'rule_key',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Map of rule_key => enabled (defaults handled by the caller).
     */
    public static function states(): \Illuminate\Support\Collection
    {
        return static::pluck('enabled', 'rule_key');
    }
}

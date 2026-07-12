<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgriculturalInput extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_date',
        'input',
        'supplier',
        'qty',
        'unit_price',
        'total',
        'expiry',
        'used_on',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'expiry' => 'date',
        'used_on' => 'date',
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];
}

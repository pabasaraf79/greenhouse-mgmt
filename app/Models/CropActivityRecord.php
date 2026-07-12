<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CropActivityRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity',
        'date',
        'field_block',
        'variety',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}

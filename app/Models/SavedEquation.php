<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedEquation extends Model
{
    protected $fillable = ['equation_name', 'formula'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

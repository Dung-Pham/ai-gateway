<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelWeight extends Model
{
    protected $guarded = [];

    protected $casts = ['weight' => 'float'];
}

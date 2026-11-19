<?php

namespace App\Models;

use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Model;

class Capability extends Model
{
    protected $guarded = [];

    protected $casts = ['risk_level' => RiskLevel::class, 'enabled' => 'boolean'];
}

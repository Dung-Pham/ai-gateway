<?php

namespace App\Models;

use App\Enums\Severity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'severity' => Severity::class,
        'opened_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('resolved_at');
    }
}

<?php

namespace App\Models;

use App\Enums\UsageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['ts' => 'datetime', 'status' => UsageStatus::class, 'estimated' => 'boolean'];

    public function app(): BelongsTo
    {
        return $this->belongsTo(ClientApp::class, 'app_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

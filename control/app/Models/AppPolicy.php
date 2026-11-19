<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppPolicy extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['allowed_models' => 'array', 'enabled' => 'boolean'];

    public function app(): BelongsTo
    {
        return $this->belongsTo(ClientApp::class, 'app_id');
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(Capability::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function fallbackPool(): BelongsTo
    {
        return $this->belongsTo(Pool::class, 'fallback_pool_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

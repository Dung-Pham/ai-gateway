<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => AccountStatus::class,
        'cooldown_until' => 'datetime',
        'quota_updated_at' => 'datetime',
        'last_auth_check_at' => 'datetime',
        'pct_5h' => 'float',
        'pct_week' => 'float',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class);
    }

    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(Pool::class, 'pool_members')->withPivot(['priority', 'weight']);
    }

    /** Mức dùng cao nhất trong các cửa sổ đo được; null nếu chưa đo. */
    public function getPeakPctAttribute(): ?float
    {
        $values = array_filter([$this->pct_5h, $this->pct_week], fn ($v) => $v !== null);

        return $values ? max($values) : null;
    }

    public function getQuotaSourceLabelAttribute(): string
    {
        return match ($this->quota_source) {
            'cli' => 'đọc từ CLI',
            'internal' => 'đếm nội bộ',
            'estimate' => 'ước lượng',
            default => 'chưa đo được',
        };
    }
}

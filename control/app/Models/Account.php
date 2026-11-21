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
        'logged_in_at' => 'datetime',
        'login_expires_at' => 'datetime',
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

    /** Số ngày còn lại tới khi phải đăng nhập lại (làm tròn lên). <= 0 nghĩa là đã hết hạn; null nếu chưa biết hạn. */
    public function getLoginDaysLeftAttribute(): ?int
    {
        if ($this->login_expires_at === null) {
            return null;
        }
        $sec = $this->login_expires_at->getTimestamp() - now()->getTimestamp();

        return $sec <= 0 ? 0 : (int) ceil($sec / 86400);
    }

    public function getLoginLevelAttribute(): string
    {
        return \App\Services\Gateway\LoginPolicy::level($this->login_days_left);
    }

    public function getLoginSourceLabelAttribute(): string
    {
        return match ($this->login_expires_source) {
            'cli' => 'đọc từ CLI',
            'estimate' => 'ước tính theo cài đặt',
            default => 'chưa rõ hạn',
        };
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

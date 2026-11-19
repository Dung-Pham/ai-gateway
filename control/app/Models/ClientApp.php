<?php

namespace App\Models;

use App\Enums\AppStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ứng dụng của tổ chức dùng Cổng AI (bảng `apps`). */
class ClientApp extends Model
{
    protected $table = 'apps';

    protected $guarded = [];

    protected $casts = ['status' => AppStatus::class];

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'app_user', 'app_id', 'user_id');
    }

    public function keys(): HasMany
    {
        return $this->hasMany(AppKey::class, 'app_id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(AppPolicy::class, 'app_id');
    }

    public function limits(): HasMany
    {
        return $this->hasMany(AppLimit::class, 'app_id');
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class, 'app_id');
    }

    /** Đơn vị quy đổi đã dùng từ đầu ngày (chỉ tính lượt thành công). */
    public function unitsToday(): int
    {
        return (int) $this->usageEvents()->where('ts', '>=', now()->startOfDay())->where('status', 'ok')->sum('weighted_units');
    }

    public function dailyLimit(): ?int
    {
        return $this->limits()->whereNull('capability_code')->value('units_per_day');
    }
}

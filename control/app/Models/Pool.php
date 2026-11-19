<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pool extends Model
{
    protected $guarded = [];

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'pool_members')->withPivot(['priority', 'weight'])->orderByPivot('priority');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PoolMember::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(AppPolicy::class);
    }

    public static function strategies(): array
    {
        return [
            'least_used' => 'Ít dùng nhất trước',
            'priority' => 'Theo thứ tự ưu tiên',
            'round_robin' => 'Xoay vòng',
        ];
    }
}

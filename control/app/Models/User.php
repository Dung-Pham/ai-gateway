<?php

namespace App\Models;

use App\Enums\Role;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => Role::class];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role instanceof Role;
    }

    /** Mặc định từ chối: người dùng không có vai trò hợp lệ không làm được gì. */
    public function allows(string $ability): bool
    {
        return $this->role instanceof Role && $this->role->can($ability);
    }

    public function isAppOwner(): bool
    {
        return $this->role === Role::AppOwner;
    }

    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(ClientApp::class, 'app_user', 'user_id', 'app_id');
    }
}

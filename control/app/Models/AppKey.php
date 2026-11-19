<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppKey extends Model
{
    protected $guarded = [];

    protected $hidden = ['key_hash'];

    protected $casts = ['last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function app(): BelongsTo
    {
        return $this->belongsTo(ClientApp::class, 'app_id');
    }

    /** Khóa chỉ hiện đúng một lần khi tạo; trong CSDL chỉ có bản băm HMAC. */
    public static function issue(ClientApp $app, string $name): array
    {
        $plain = 'gw_' . bin2hex(random_bytes(20));
        $key = static::create([
            'app_id' => $app->id,
            'name' => $name,
            'key_prefix' => substr($plain, 0, 11),
            'key_hash' => hash_hmac('sha256', $plain, (string) config('app.key')),
        ]);

        return [$key, $plain];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Cài đặt dạng khóa-giá trị. Giá trị nhạy cảm (ví dụ địa chỉ webhook) được mã hóa khi lưu. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['encrypted' => 'boolean'];

    public static function read(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);
        if (! $row || $row->value === null) {
            return $default;
        }
        $raw = $row->encrypted ? Crypt::decryptString($row->value) : $row->value;
        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    public static function write(string $key, mixed $value, bool $encrypted = false): void
    {
        $raw = is_string($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : json_encode($value, JSON_UNESCAPED_UNICODE);
        static::updateOrCreate(['key' => $key], ['value' => $encrypted ? Crypt::encryptString($raw) : $raw, 'encrypted' => $encrypted]);
    }

    public static function has(string $key): bool
    {
        return static::whereKey($key)->whereNotNull('value')->exists();
    }

    public static function forget(string $key): void
    {
        static::whereKey($key)->delete();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    public static function getValue(string $key, $default = null)
    {
        $row = static::query()->where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key, $default ? '1' : '0');
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    public static function setValue(string $key, string $value, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $userId]
        );
    }
}

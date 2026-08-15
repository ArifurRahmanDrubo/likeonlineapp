<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrSetting extends Model
{
    use HasFactory;

    protected $table = 'hr_settings';

    protected $guarded = [];

    /**
     * Read a setting value, falling back to a default when unset.
     */
    public static function getValue(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();

        return $row ? $row->value : $default;
    }

    /**
     * Upsert a setting value.
     */
    public static function setValue(string $key, $value)
    {
        return static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}

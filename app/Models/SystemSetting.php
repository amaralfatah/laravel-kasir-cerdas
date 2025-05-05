<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'key',
        'value',
        'is_public',
        'updated_by',
    ];

    const CREATED_AT = null;

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Get setting value
    public static function getValue($key, $category = 'general', $default = null)
    {
        $setting = self::where('category', $category)->where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    // Set setting value
    public static function setValue($key, $value, $category = 'general', $userId = null)
    {
        $setting = self::where('category', $category)->where('key', $key)->first();

        if ($setting) {
            $setting->value = $value;
            $setting->updated_by = $userId;
            $setting->save();
            return $setting;
        }

        return self::create([
            'category' => $category,
            'key' => $key,
            'value' => $value,
            'updated_by' => $userId,
        ]);
    }
}

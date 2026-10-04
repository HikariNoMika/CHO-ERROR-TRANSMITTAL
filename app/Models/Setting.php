<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'group',
    ];

    public static function get(string $key, $default = null)
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, $value, string $description = null, string $group = 'general'): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description, 'group' => $group]
        );
    }

    public static function getHeadOfClinic(): string
    {
        return static::get('head_of_clinic', 'Dr. Juan Dela Cruz');
    }

    public static function getFacilityName(): string
    {
        return static::get('facility_name', 'MCA Medical Center');
    }

    public static function getFacilityAddress(): string
    {
        return static::get('facility_address', '');
    }
}
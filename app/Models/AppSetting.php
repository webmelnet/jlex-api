<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'label'];

    /**
     * Return all settings as a flat key => value object.
     */
    public static function asObject(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}

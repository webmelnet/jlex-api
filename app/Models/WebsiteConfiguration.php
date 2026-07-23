<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteConfiguration extends Model
{
    protected $fillable = ['key', 'value', 'label'];

    /**
     * Return all configurations as a flat key => value object.
     */
    public static function asObject(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}

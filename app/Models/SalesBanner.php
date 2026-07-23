<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesBanner extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'badge_text',
        'cta_text',
        'cta_link',
        'image_path',
        'bg_color',
        'text_color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}

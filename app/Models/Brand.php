<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Brand has exactly one image via the polymorphic Image model.
     */
    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    /**
     * Get all images for this product
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable')->ordered();
    }

    /**
     * Convenience accessor — returns the image URL or null.
     * Keeps backward compatibility with any code referencing $brand->logo_url.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->image?->url;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Auto-uppercase the code before saving
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = $value ? strtoupper(trim($value)) : null;
    }
}
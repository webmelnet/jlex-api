<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'imageable_id',
        'imageable_type',
        'url',
        'path',
        'filename',
        'original_filename',
        'file_size',
        'mime_type',
        'width',
        'height',
        'is_primary',
        'position',
        'alt_text',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'is_primary' => 'boolean',
        'position' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the parent imageable model (Product, Category, etc.)
     */
    public function imageable()
    {
        return $this->morphTo();
    }

    /**
     * Scope to get only primary images
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to order by position
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position', 'asc');
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute()
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($this->file_size, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    /**
     * Get image dimensions as string
     */
    public function getDimensionsAttribute()
    {
        if (!$this->width || !$this->height) {
            return null;
        }

        return "{$this->width} x {$this->height}";
    }

    /**
     * Get optimization percentage from metadata
     */
    public function getOptimizationPercentageAttribute()
    {
        if (!$this->metadata || !isset($this->metadata['was_optimized']) || !$this->metadata['was_optimized']) {
            return null;
        }

        if (isset($this->metadata['original_size']) && isset($this->metadata['final_size'])) {
            $original = $this->metadata['original_size'];
            $final = $this->metadata['final_size'];
            
            if ($original > 0) {
                return round((($original - $final) / $original) * 100, 2);
            }
        }

        return null;
    }

    /**
     * Check if image was optimized
     */
    public function getWasOptimizedAttribute()
    {
        return $this->metadata['was_optimized'] ?? false;
    }

    /**
     * Set as primary image (and unset others)
     */
    public function setAsPrimary()
    {
        // Unset all other primary images for this model
        self::where('imageable_type', $this->imageable_type)
            ->where('imageable_id', $this->imageable_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Set this as primary
        $this->update(['is_primary' => true]);

        return $this;
    }

    /**
     * Reorder images
     */
    public static function reorder(array $imageIds)
    {
        foreach ($imageIds as $position => $imageId) {
            self::where('id', $imageId)->update(['position' => $position]);
        }
    }
}
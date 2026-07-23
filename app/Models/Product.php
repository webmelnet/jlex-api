<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'description',
        'category_id',
        'brand_id',
        'supplier_id',
        'cost',
        'price',
        'sale_price',
        'sale_mode',
        'sale_start_at',
        'sale_end_at',
        'stock_quantity',
        'reorder_level',
        'unit',
        'track_inventory',
        'is_active',
        'stock_verified',
        'notes',
        'expiration_date',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'reorder_level' => 'integer',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
        'stock_verified' => 'boolean',
        'sale_start_at' => 'datetime',
        'sale_end_at' => 'datetime',
        'expiration_date' => 'date',
    ];

    protected $appends = ['is_on_sale', 'effective_price', 'is_near_expiration', 'is_expired'];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get all images for this product
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable')->ordered();
    }

    /**
     * Get the primary/main image
     */
    public function primaryImage()
    {
        return $this->morphOne(Image::class, 'imageable')->where('is_primary', true);
    }

    /**
     * Get the primary image URL (for backward compatibility)
     */
    public function getImageAttribute()
    {
        return $this->primaryImage?->url;
    }

    /**
     * Get all image URLs as array (for backward compatibility)
     */
    public function getImagesUrlsAttribute()
    {
        return $this->images->pluck('url')->toArray();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'reorder_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    // Accessors & Mutators
    public function getIsLowStockAttribute()
    {
        return $this->stock_quantity <= $this->reorder_level;
    }

    public function getIsOutOfStockAttribute()
    {
        return $this->stock_quantity <= 0;
    }

    public function getProfitMarginAttribute()
    {
        if ($this->cost > 0) {
            return (($this->price - $this->cost) / $this->cost) * 100;
        }
        return 0;
    }

    public function getIsOnSaleAttribute(): bool
    {
        if (!$this->sale_price || !$this->sale_mode) {
            return false;
        }

        return match($this->sale_mode) {
            'manual'    => true,
            'scheduled' => $this->sale_start_at && $this->sale_end_at
                            && now()->between($this->sale_start_at, $this->sale_end_at),
            'stock'     => $this->stock_quantity > 0,
            default     => false,
        };
    }

    public function getEffectivePriceAttribute(): ?string
    {
        return $this->is_on_sale ? $this->sale_price : $this->price;
    }

    public function getIsNearExpirationAttribute(): bool
    {
        if (!$this->expiration_date) {
            return false;
        }
        return now()->diffInDays($this->expiration_date, false) <= 30
            && $this->expiration_date >= now()->startOfDay();
    }

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiration_date) {
            return false;
        }
        return $this->expiration_date < now()->startOfDay();
    }

    public function scopeNearExpiration($query, int $days = 30)
    {
        return $query->whereNotNull('expiration_date')
            ->where('expiration_date', '>=', now()->startOfDay())
            ->where('expiration_date', '<=', now()->addDays($days)->endOfDay());
    }

    // Methods
    public function updateStock($quantity, $type = 'sale')
    {
        if (!$this->track_inventory) {
            return;
        }

        $oldQuantity = $this->stock_quantity;

        if ($type === 'sale') {
            $this->stock_quantity -= $quantity;
        } else {
            $this->stock_quantity += $quantity;
        }

        $this->save();

        return $oldQuantity;
    }

    /**
     * Add image to product
     */
    public function addImage(array $imageData, bool $isPrimary = false)
    {
        // If this is set as primary, unset other primary images
        if ($isPrimary) {
            $this->images()->update(['is_primary' => false]);
        }

        // Set position as the next in sequence
        $nextPosition = $this->images()->max('position') + 1;

        return $this->images()->create(array_merge($imageData, [
            'is_primary' => $isPrimary,
            'position' => $nextPosition,
        ]));
    }

    /**
     * Set primary image by image ID
     */
    public function setPrimaryImage(int $imageId)
    {
        $image = $this->images()->find($imageId);
        
        if ($image) {
            $image->setAsPrimary();
        }

        return $image;
    }

    /**
     * Remove image
     */
    public function removeImage(int $imageId)
    {
        return $this->images()->where('id', $imageId)->delete();
    }
}
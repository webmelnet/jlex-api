<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'product_id',
        'user_id',
        'quantity_before',
        'quantity_adjusted',
        'quantity_after',
        'type',
        'reason',
        'notes',
        'adjustment_date',
    ];

    protected $casts = [
        'quantity_before' => 'integer',
        'quantity_adjusted' => 'integer',
        'quantity_after' => 'integer',
        'adjustment_date' => 'datetime',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

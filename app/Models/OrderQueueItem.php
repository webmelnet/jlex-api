<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderQueueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_queue_id',
        'product_id',
        'quantity',
        'price',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relationships
    public function orderQueue()
    {
        return $this->belongsTo(OrderQueue::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

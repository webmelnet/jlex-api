<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
        'cost',
        'subtotal',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relationships
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Methods
    public function receiveQuantity($quantity)
    {
        $this->quantity_received += $quantity;
        $this->save();

        // Update product stock
        $this->product->updateStock($quantity, 'purchase');

        // Create stock movement
        StockMovement::create([
            'product_id' => $this->product_id,
            'type' => 'purchase',
            'quantity' => $quantity,
            'quantity_before' => $this->product->stock_quantity - $quantity,
            'quantity_after' => $this->product->stock_quantity,
            'reference_type' => 'purchase_order_id',
            'reference_id' => $this->purchase_order_id,
            'user_id' => auth()->id(),
        ]);

        // Check if PO is fully received
        $this->purchaseOrder->checkIfFullyReceived();
    }
}

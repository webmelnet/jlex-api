<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'user_id',
        'order_date',
        'expected_date',
        'received_date',
        'status',
        'subtotal',
        'tax',
        'discount',
        'shipping',
        'total',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOrdered($query)
    {
        return $query->where('status', 'ordered');
    }

    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    // Methods
    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->total = $this->subtotal + $this->tax + $this->shipping - $this->discount;
        $this->save();
    }

    public function markAsReceived()
    {
        $this->status = 'received';
        $this->received_date = now();
        $this->save();
    }

    public function checkIfFullyReceived()
    {
        $allReceived = $this->items()->whereColumn('quantity_received', '>=', 'quantity_ordered')->count() === $this->items()->count();
        
        if ($allReceived && $this->status !== 'received') {
            $this->markAsReceived();
        } elseif (!$allReceived && $this->status === 'received') {
            $this->status = 'partial';
            $this->save();
        }
    }
}

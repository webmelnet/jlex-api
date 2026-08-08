<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_type',
        'staff_user_id',
        'user_id',
        'sale_date',
        'subtotal',
        'tax',
        'discount',
        'loyalty_points_used',
        'total',
        'amount_paid',
        'change_amount',
        'payment_method',
        'ewallet_provider',
        'ewallet_reference',
        'ewallet_screenshot',
        'status',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'loyalty_points_used' => 'integer',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    protected $appends = [
        'customer_name',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getCustomerNameAttribute()
    {
        return $this->customer?->name;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staffUser()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function queue()
    {
        return $this->hasOne(OrderQueue::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class, 'sale_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'refunded']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('sale_date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('sale_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('sale_date', now()->month)
                    ->whereYear('sale_date', now()->year);
    }

    // Methods
    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->total = $this->subtotal + $this->tax - $this->discount;
        $this->change_amount = $this->amount_paid - $this->total;
        $this->save();
    }
}

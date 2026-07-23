<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    use HasFactory;

    protected $fillable = [
        'exchange_number',
        'original_sale_id',
        'user_id',
        'exchange_date',
        'return_total',
        'replacement_total',
        'amount_due',
        'amount_paid',
        'payment_method',
        'ewallet_reference',
        'status',
        'notes',
    ];

    protected $casts = [
        'exchange_date'     => 'datetime',
        'return_total'      => 'decimal:2',
        'replacement_total' => 'decimal:2',
        'amount_due'        => 'decimal:2',
        'amount_paid'       => 'decimal:2',
    ];

    public function originalSale()
    {
        return $this->belongsTo(Sale::class, 'original_sale_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ExchangeItem::class);
    }

    public function returnedItems()
    {
        return $this->hasMany(ExchangeItem::class)->where('type', 'returned');
    }

    public function replacementItems()
    {
        return $this->hasMany(ExchangeItem::class)->where('type', 'replacement');
    }
}

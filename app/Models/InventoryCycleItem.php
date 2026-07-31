<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCycleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_cycle_id',
        'product_id',
        'beginning_stock',
        'added',
        'deducted',
        'non_cash_deducted',
        'current_stock',
        'staff_input',
        'variance',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'beginning_stock' => 'integer',
        'added' => 'integer',
        'deducted' => 'integer',
        'non_cash_deducted' => 'integer',
        'current_stock' => 'integer',
        'staff_input' => 'integer',
        'variance' => 'integer',
    ];

    public function cycle()
    {
        return $this->belongsTo(InventoryCycle::class, 'inventory_cycle_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

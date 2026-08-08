<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'sale_id',
        'user_id',
        'return_date',
        'refund_total',
        'status',
        'policy_override',
        'notes',
    ];

    protected $casts = [
        'return_date'     => 'datetime',
        'refund_total'    => 'decimal:2',
        'policy_override' => 'boolean',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}

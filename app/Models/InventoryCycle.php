<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'status',
        'started_at',
        'closed_at',
        'started_by',
        'closed_by',
        'notes',
    ];

    protected $casts = [
        'period' => 'date',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(InventoryCycleItem::class);
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}

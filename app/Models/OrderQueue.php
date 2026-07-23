<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderQueue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'queue_number',
        'customer_id',
        'customer_name',
        'customer_type',
        'created_by_user_id',
        'claimed_by_user_id',
        'claimed_at',
        'editing_by_user_id',
        'editing_started_at',
        'sale_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'editing_started_at' => 'datetime',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function claimedBy()
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function editingBy()
    {
        return $this->belongsTo(User::class, 'editing_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderQueueItem::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // Scopes
    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeClaimed($query)
    {
        return $query->where('status', 'claimed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['queued', 'claimed']);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'loyalty_points',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'loyalty_points' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function addLoyaltyPoints($points)
    {
        $this->loyalty_points += $points;
        $this->save();
    }

    public function deductLoyaltyPoints($points)
    {
        if ($this->loyalty_points >= $points) {
            $this->loyalty_points -= $points;
            $this->save();
            return true;
        }
        return false;
    }

    public function getTotalPurchasesAttribute()
    {
        return $this->sales()->sum('total');
    }
}

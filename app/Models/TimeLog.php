<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in',
        'clock_out',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'clock_in'  => 'datetime',
        'clock_out' => 'datetime',
    ];

    protected $appends = ['duration_minutes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('work_date', today());
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('work_date', $date);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('work_date', [$from, $to]);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('clock_out');
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->clock_in || !$this->clock_out) {
            return null;
        }

        return $this->clock_in->diffInMinutes($this->clock_out);
    }
}

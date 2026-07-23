<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInvitation extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'token'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
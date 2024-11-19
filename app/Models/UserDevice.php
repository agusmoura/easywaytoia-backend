<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'ip_address',
        'user_agent',
        'token',
        'last_activity'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
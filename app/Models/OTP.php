<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OTP extends Model
{
    protected $table = 'otps';
    
    protected $fillable = [
        'user_id',
        'otp',
        'expires_at',
        'is_used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid()
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }
} 
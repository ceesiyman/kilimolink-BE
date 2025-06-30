<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_message_id',
        'user_id'
    ];

    public function message()
    {
        return $this->belongsTo(CommunityMessage::class, 'community_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

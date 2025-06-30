<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReplyLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_reply_id',
        'user_id'
    ];

    public function reply()
    {
        return $this->belongsTo(MessageReply::class, 'message_reply_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

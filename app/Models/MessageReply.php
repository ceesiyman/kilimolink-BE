<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageReply extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'community_message_id',
        'user_id',
        'content',
        'parent_reply_id'
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'community_message_id' => 'integer'
    ];

    public function message()
    {
        return $this->belongsTo(CommunityMessage::class, 'community_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentReply()
    {
        return $this->belongsTo(MessageReply::class, 'parent_reply_id');
    }

    public function replies()
    {
        return $this->hasMany(MessageReply::class, 'parent_reply_id')->with('user');
    }

    public function likes()
    {
        return $this->hasMany(ReplyLike::class);
    }

    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'reply_likes', 'message_reply_id', 'user_id')
            ->withTimestamps();
    }

    public function toggleLike($userId)
    {
        $hasLiked = $this->likedBy()->where('user_id', $userId)->exists();

        if ($hasLiked) {
            $this->likedBy()->detach($userId);
            $this->decrement('likes_count');
            return false;
        } else {
            $this->likedBy()->attach($userId);
            $this->increment('likes_count');
            return true;
        }
    }

    public function getDepthAttribute()
    {
        $depth = 0;
        $parent = $this->parentReply;
        
        while ($parent) {
            $depth++;
            $parent = $parent->parentReply;
        }
        
        return $depth;
    }
}

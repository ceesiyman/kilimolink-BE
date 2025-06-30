<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'category',
        'tags',
        'is_pinned',
        'is_announcement'
    ];

    protected $casts = [
        'tags' => 'array',
        'is_pinned' => 'boolean',
        'is_announcement' => 'boolean',
        'views_count' => 'integer',
        'likes_count' => 'integer',
        'replies_count' => 'integer',
        'last_reply_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class)->orderBy('order');
    }

    public function replies()
    {
        return $this->hasMany(MessageReply::class)->whereNull('parent_reply_id')->with('user', 'replies.user');
    }

    public function allReplies()
    {
        return $this->hasMany(MessageReply::class)->with('user');
    }

    public function likes()
    {
        return $this->hasMany(MessageLike::class);
    }

    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'message_likes')
            ->withTimestamps();
    }

    public function incrementViews()
    {
        $this->increment('views_count');
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

    public function updateRepliesCount()
    {
        $this->replies_count = $this->allReplies()->count();
        $this->last_reply_at = $this->allReplies()->latest()->first()?->created_at;
        $this->save();
    }

    public function scopeWithFilters($query, $request)
    {
        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        if ($request->pinned) {
            $query->where('is_pinned', true);
        }

        if ($request->announcement) {
            $query->where('is_announcement', true);
        }

        return $query;
    }
}

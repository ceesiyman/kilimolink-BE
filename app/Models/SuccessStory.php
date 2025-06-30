<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuccessStory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'location',
        'crop_type',
        'yield_improvement',
        'yield_unit',
        'is_featured'
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'yield_improvement' => 'decimal:2',
        'views_count' => 'integer',
        'likes_count' => 'integer',
        'comments_count' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(StoryImage::class)->orderBy('order');
    }

    public function comments()
    {
        return $this->hasMany(StoryComment::class)->whereNull('parent_id')->with('user', 'replies.user');
    }

    public function allComments()
    {
        return $this->hasMany(StoryComment::class)->with('user');
    }

    public function likes()
    {
        return $this->hasMany(StoryLike::class);
    }

    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'story_likes')
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

    public function updateCommentsCount()
    {
        $this->comments_count = $this->allComments()->count();
        $this->save();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'content',
        'is_featured',
        'tags'
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'tags' => 'array',
        'views_count' => 'integer',
        'likes_count' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tip) {
            if (empty($tip->slug)) {
                $tip->slug = Str::slug($tip->title);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(TipCategory::class, 'category_id');
    }

    public function savedBy()
    {
        return $this->belongsToMany(User::class, 'saved_tips')
            ->withTimestamps();
    }

    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'tip_likes')
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

    public function toggleSave($userId)
    {
        $hasSaved = $this->savedBy()->where('user_id', $userId)->exists();

        if ($hasSaved) {
            $this->savedBy()->detach($userId);
            return false;
        } else {
            $this->savedBy()->attach($userId);
            return true;
        }
    }
}

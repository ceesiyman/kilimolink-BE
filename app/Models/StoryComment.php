<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoryComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'success_story_id',
        'user_id',
        'comment',
        'parent_id'
    ];

    public function successStory()
    {
        return $this->belongsTo(SuccessStory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(StoryComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(StoryComment::class, 'parent_id');
    }
}

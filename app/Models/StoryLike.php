<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StoryLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'success_story_id',
        'user_id'
    ];

    public function successStory()
    {
        return $this->belongsTo(SuccessStory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
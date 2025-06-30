<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StoryImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'success_story_id',
        'image_path',
        'caption',
        'order'
    ];

    public function successStory()
    {
        return $this->belongsTo(SuccessStory::class);
    }
}

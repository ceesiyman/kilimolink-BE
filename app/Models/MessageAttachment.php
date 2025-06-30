<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_message_id',
        'file_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'caption',
        'order'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'order' => 'integer'
    ];

    public function message()
    {
        return $this->belongsTo(CommunityMessage::class, 'community_message_id');
    }

    public function getUrlAttribute()
    {
        return asset($this->file_path);
    }

    public function getFileTypeIconAttribute()
    {
        switch ($this->file_type) {
            case 'image':
                return '🖼️';
            case 'document':
                return '📄';
            case 'video':
                return '🎥';
            case 'audio':
                return '🎵';
            default:
                return '📎';
        }
    }

    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

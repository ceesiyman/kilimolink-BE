<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'image',
        'is_featured',
        'stock',
        'location',
        'user_id'
    ];

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

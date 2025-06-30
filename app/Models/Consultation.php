<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Consultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'farmer_id',
        'expert_id',
        'consultation_date',
        'description',
        'status',
        'expert_notes',
        'decline_reason'
    ];

    protected $casts = [
        'consultation_date' => 'datetime'
    ];

    public function farmer()
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function expert()
    {
        return $this->belongsTo(User::class, 'expert_id');
    }
}

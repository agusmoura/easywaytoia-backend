<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'description',
        'is_active',
        'stripe_price_id',
        'courses'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'courses' => 'array'
    ];

    public function setCourses(array $courseIdentifiers)
    {
        $this->courses = $courseIdentifiers;
        $this->save();
    }
} 


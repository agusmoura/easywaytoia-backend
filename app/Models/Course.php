<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'description',
        'slug',
        'stripe_price_id',
        'is_active',
        'price'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];


    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}

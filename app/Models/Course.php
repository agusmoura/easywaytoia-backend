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
        'type',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];


    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}

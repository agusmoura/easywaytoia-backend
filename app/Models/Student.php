<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Student extends Model
{
    use HasFactory;

    protected $table = 'student';

    protected $fillable = [
        'user_id',
        'name',
        'last_name',
        'country',
        'phone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'enrollments', 'user_id', 'course_id')
                    ->withPivot(['created_at', 'payment_id'])
                    ->withTimestamps();
    }

    public function bundles()
    {
        return $this->belongsToMany(Bundle::class, 'enrollments', 'user_id', 'bundle_id')
                    ->withPivot(['created_at', 'payment_id'])
                    ->withTimestamps();
    }
}

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

    public function bundles()
    {
        return $this->belongsToMany(Bundle::class, 'bundle_course');
    }

    public function paymentLinks()
    {
        return $this->belongsToMany(PaymentLink::class, 'course_payment_link')
                    ->withTimestamps();
    }

    public function coursePaymentLinks()
    {
        return $this->hasMany(CoursePaymentLink::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}

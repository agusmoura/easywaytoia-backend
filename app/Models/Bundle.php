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
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'bundle_course');
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
} 
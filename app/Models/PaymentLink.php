<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'link',
        'identifier',
        'provider',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_payment_link')
                    ->withTimestamps();
    }

    public function bundles()
    {
        return $this->belongsToMany(Bundle::class, 'course_payment_link')
                    ->withTimestamps();
    }

    // Helper method to get all purchasable items (courses or bundles)
    public function purchasableItems()
    {
        return $this->hasMany(CoursePaymentLink::class);
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursePaymentLink extends Model
{
    protected $table = 'course_payment_link';

    protected $fillable = [
        'course_id',
        'bundle_id',
        'payment_link_id'
    ];

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    // Helper method to get the purchasable item (either course or bundle)
    public function getPurchasableItem()
    {
        return $this->course_id ? $this->course : $this->bundle;
    }
} 
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

    public function products()
    {
        return $this->belongsToMany(Product::class, 'enrollments', 'user_id', 'product_id')
                    ->withPivot(['created_at', 'payment_id'])
                    ->withTimestamps();
    }

    public function courses()
    {
        return $this->products()->where('type', 'course');
    }

    public function bundles()
    {
        return $this->products()->where('type', 'bundle');
    }

    public function maximizers()
    {
        return $this->products()->where('type', 'maximizer');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

    public static function createCourse(array $data)
    {
        $validator = Validator::make($data, [
            'identifier' => ['required', 'string', 'max:255', Rule::unique('courses')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['required', 'string'],
            'is_active' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return self::create([
            'identifier' => $data['identifier'],
            'name' => $data['name'],
            'description' => $data['description'],
            'slug' => Str::slug($data['name']),
            'stripe_price_id' => $data['stripe_price_id'],
            'price' => $data['price'],
            'is_active' => $data['is_active'] ?? true
        ]);
    }

    public function updateCourse(array $data)
    {
        $validator = Validator::make($data, [
            'identifier' => ['string', 'max:255', Rule::unique('courses')->ignore($this->id)],
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['string'],
            'is_active' => ['boolean'],
            'price' => ['numeric', 'min:0']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $this->update($data);

        if (isset($data['name'])) {
            $this->slug = Str::slug($data['name']);
            $this->save();
        }

        return $this;
    }

    public function updatePrice(array $data)
    {
        $validator = Validator::make($data, [
            'stripe_price_id' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $this->update([
            'stripe_price_id' => $data['stripe_price_id'],
            'price' => $data['price']
        ]);

        return $this;
    }
}

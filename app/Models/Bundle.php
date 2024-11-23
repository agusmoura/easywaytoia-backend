<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Course;

class Bundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'description',
        'is_active',
        'stripe_price_id',
        'courses',
        'price'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'courses' => 'array'
    ];

    public static function createBundle(array $data)
    {
        $validator = Validator::make($data, [
            'identifier' => ['required', 'string', 'max:255', Rule::unique('bundles')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['required', 'string'],
            'courses' => ['required', 'array'],
            'courses.*' => ['string', 'exists:courses,identifier'],
            'is_active' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Verificar si existen los cursos
        $courses = Course::whereIn('identifier', $data['courses'])->get();
        if ($courses->isEmpty()) {
            throw new \Exception('No existen los cursos', 422);
        }

        return self::create([
            'identifier' => $data['identifier'],
            'name' => $data['name'],
            'description' => $data['description'],
            'stripe_price_id' => $data['stripe_price_id'],
            'courses' => $data['courses'],
            'is_active' => $data['is_active'] ?? true,
            'price' => $data['price']
        ]);
    }

    public function updateBundle(array $data)
    {
        $validator = Validator::make($data, [
            'identifier' => ['string', 'max:255', Rule::unique('bundles')->ignore($this->id)],
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['string'],
            'courses' => ['array'],
            'courses.*' => ['string', 'exists:courses,identifier'],
            'is_active' => ['boolean'],
            'price' => ['numeric', 'min:0']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $this->update($data);
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

        $this->update($data);
        return $this;
    }

    public function setCourses(array $courseIdentifiers)
    {
        $this->courses = $courseIdentifiers;
        $this->save();
    }
} 


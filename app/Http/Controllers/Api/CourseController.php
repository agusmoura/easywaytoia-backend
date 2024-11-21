<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
class CourseController extends Controller
{
    public function store(Request $request)
    {

      

        $request->validate([
            'identifier' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'stripe_price_id' => 'required|string',
            'is_active' => 'boolean'
        ]);

        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255', Rule::unique('courses')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        if($validator->fails()){
            throw new \Exception($validator->errors(), 422);
        }

        try {
            $course = Course::create([
                'identifier' => $request->identifier,
                'name' => $request->name,
                'description' => $request->description,
                'slug' => Str::slug($request->name),
                'stripe_price_id' => $request->stripe_price_id,
                'is_active' => $request->is_active ?? true
            ]);

            return response()->json([
                'message' => 'Curso creado exitosamente',
                'course' => $course
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el curso',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Course $course)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['string', 'max:255'],
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['string'],
            'is_active' => ['boolean'],
        ]);

        if($validator->fails()){
            throw new \Exception($validator->errors(), 422);
        }

        try {
            $course->update($request->only([
                'name',
                'description',
                'stripe_price_id',
                'is_active'
            ]));

            if ($request->has('name')) {
                $course->slug = Str::slug($request->name);
                $course->save();
            }

            return response()->json([
                'message' => 'Curso actualizado exitosamente',
                'course' => $course
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el curso',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePrice(Request $request, Course $course)
    {
        $validator = Validator::make($request->all(), [
            'stripe_price_id' => ['required', 'string']
        ]);

        if($validator->fails()){
            throw new \Exception($validator->errors(), 422);
        }

        try {
            $course->update([
                'stripe_price_id' => $request->stripe_price_id
            ]);

            return response()->json([
                'message' => 'Precio actualizado exitosamente',
                'course' => $course
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el precio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Course;
class BundleController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255', Rule::unique('bundles')->ignore($request->identifier)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['required', 'string'],
            'courses' => ['required', 'array'],
            'courses.*' => ['string', 'exists:courses,identifier'],
            'is_active' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0']
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        /* ver si existen los cursos */
        $courses = Course::whereIn('identifier', $request->courses)->get();
        if($courses->isEmpty()){
            throw new \Exception('No existen los cursos', 422);
        }

        try {

            $bundle = Bundle::create([
                'identifier' => $request->identifier,
                'name' => $request->name,
                'description' => $request->description,
                'stripe_price_id' => $request->stripe_price_id,
                'courses' => $request->courses,
                'is_active' => $request->is_active ?? true,
                'price' => $request->price
            ]);

            return response()->json([
                'message' => 'Bundle creado exitosamente',
                'bundle' => $bundle
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $bundleId)
    {
        try {
            $bundle = Bundle::findOrFail($bundleId);

            $validator = Validator::make($request->all(), [
                'identifier' => ['string', 'max:255', Rule::unique('bundles')->ignore($bundle->id)],
                'name' => ['string', 'max:255'],
                'description' => ['nullable', 'string'],
                'stripe_price_id' => ['string'],
                'courses' => ['array'],
                'courses.*' => ['string', 'exists:courses,identifier'],
                'is_active' => ['boolean'],
                'price' => ['numeric', 'min:0']
            ]);

            if($validator->fails()){
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bundle->update($request->only([
                'identifier',
                'name',
                'description',
                'stripe_price_id',
                'courses',
                'is_active',
                'price'
            ]));

            return response()->json([
                'message' => 'Bundle actualizado exitosamente',
                'bundle' => $bundle
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Bundle no encontrado',
                'error' => "No existe un bundle con el ID: {$bundleId}"
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePrice(Request $request, $bundleId)
    {
        try {
            $bundle = Bundle::findOrFail($bundleId);

            $validator = Validator::make($request->all(), [
                'stripe_price_id' => ['required', 'string'],
                'price' => ['required', 'numeric', 'min:0']
            ]);

            if($validator->fails()){
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bundle->update([
                'stripe_price_id' => $request->stripe_price_id,
                'price' => $request->price
            ]);

            return response()->json([
                'message' => 'Precio actualizado exitosamente',
                'bundle' => $bundle
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Bundle no encontrado',
                'error' => "No existe un bundle con el ID: {$bundleId}"
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el precio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 
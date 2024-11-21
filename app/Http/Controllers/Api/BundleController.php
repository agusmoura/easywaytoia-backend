<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BundleController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['required', 'string'],
            'courses' => ['required', 'array'],
            'courses.*' => ['string', 'exists:courses,identifier'],
            'is_active' => ['boolean']
        ]);

        if($validator->fails()){
            throw new \Exception($validator->errors(), 422);
        }

        try {
            $bundle = Bundle::create([
                'identifier' => $request->identifier,
                'name' => $request->name,
                'description' => $request->description,
                'stripe_price_id' => $request->stripe_price_id,
                'courses' => $request->courses,
                'is_active' => $request->is_active ?? true
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

    public function update(Request $request, Bundle $bundle)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['string', 'max:255'],
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stripe_price_id' => ['string'],
            'courses' => ['array'],
            'courses.*' => ['string', 'exists:courses,identifier'],
            'is_active' => ['boolean']
        ]);

        if($validator->fails()){
            throw new \Exception($validator->errors(), 422);
        }

        try {
            $bundle->update($request->only([
                'identifier',
                'name',
                'description',
                'stripe_price_id',
                'courses',
                'is_active'
            ]));

            return response()->json([
                'message' => 'Bundle actualizado exitosamente',
                'bundle' => $bundle
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePrice(Request $request, Bundle $bundle)
    {
@@        $validator = Validator::make($request->all(), [
            'stripe_price_id' => ['required', 'string']
        ]);

        if($validator->fails()){
            throw new \Exception($validator->errors(), 422);
        }

        try {
            $bundle->update([
                'stripe_price_id' => $request->stripe_price_id
            ]);

            return response()->json([
                'message' => 'Precio actualizado exitosamente',
                'bundle' => $bundle
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el precio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 
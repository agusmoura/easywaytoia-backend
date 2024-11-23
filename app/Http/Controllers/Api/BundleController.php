<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    public function store(Request $request)
    {
        try {
            $bundle = Bundle::createBundle($request->all());
            
            return response()->json([
                'message' => 'Bundle creado exitosamente',
                'bundle' => $bundle
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Bundle $bundle)
    {
        try {
            $bundle = $bundle->updateBundle($request->all());
            
            return response()->json([
                'message' => 'Bundle actualizado exitosamente',
                'bundle' => $bundle
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePrice(Request $request, Bundle $bundle)
    {
        try {
            $bundle = $bundle->updatePrice($request->all());
            
            return response()->json([
                'message' => 'Precio actualizado exitosamente',
                'bundle' => $bundle
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el precio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class ProductController extends Controller
{
    public function store(Request $request)
    {
        try {
            $product = Product::createProduct($request->all());
            
            return response()->json([
                'message' => 'Producto creado exitosamente',
                'product' => $product
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Product $product)
    {
        try {
            $product = $product->updateProduct($request->all());
            
            return response()->json([
                'message' => 'Producto actualizado exitosamente',
                'product' => $product
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePrice(Request $request, Product $product)
    {
        try {
            $product->updateProduct([
                'price' => $request->price,
                'stripe_price_id' => $request->stripe_price_id
            ]);
            
            return response()->json([
                'message' => 'Precio actualizado exitosamente',
                'product' => $product
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el precio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $products = Product::with('childProducts')->get();
        Log::info($products);
        if ($products->isEmpty()) {
            throw new \Exception("No hay productos disponibles", 1);
            
        }
        return response()->json($products);
    }

    public function show(Product $product)
    {
        return response()->json($product->load('childProducts'));
    }
} 
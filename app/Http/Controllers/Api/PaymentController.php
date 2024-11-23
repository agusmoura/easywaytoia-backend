<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Services\Payments\PaymentStripe;
use App\Services\Payments\PaymentUala;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function createPaymentLink(Request $request)
    {
        try {
            $result = Payment::createPaymentLink($request->all(), auth()->user());
            return response()->json($result, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function handleStripeWebhook(Request $request)
    {
        try {
            PaymentStripe::handleWebhook(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function handleUalaWebhook(Request $request)
    {
        try {
            PaymentUala::handleWebhook($request->all());
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error processing Uala webhook: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
} 
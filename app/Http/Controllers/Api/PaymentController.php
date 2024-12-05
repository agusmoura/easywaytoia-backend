<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Services\Payments\PaymentStripe;
use App\Services\Payments\PaymentUala;
use Illuminate\Support\Facades\Log;
use App\Notifications\PurchaseConfirmationNotification;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Course;
use App\Models\Bundle;
use Stripe\Webhook;

class PaymentController extends Controller
{
    private function handleSuccessfulPayment($payment)
    {
        // Actualizar estado del pago
        $payment->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        // Crear inscripciones según el tipo de compra
        if ($payment->course_id) {
            // Compra de curso individual
            Enrollment::create([
                'user_id' => $payment->user_id,
                'course_id' => $payment->course_id,
                'payment_id' => $payment->id,
                'status' => 'active',
                'enrolled_at' => now()
            ]);
        } elseif ($payment->bundle_id) {
            // Compra de bundle
            $bundle = Bundle::with('courses')->find($payment->bundle_id);
            
            foreach ($bundle->courses as $course) {
                Enrollment::create([
                    'user_id' => $payment->user_id,
                    'course_id' => $course->id,
                    'payment_id' => $payment->id,
                    'bundle_id' => $bundle->id,
                    'status' => 'active',
                    'enrolled_at' => now()
                ]);
            }
        }

        // Enviar email de confirmación
        $user = User::find($payment->user_id);
        $user->notify(new PurchaseConfirmationNotification($payment));

        // Registrar log de la transacción exitosa
        Log::info('Pago procesado exitosamente', [
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'amount' => $payment->amount,
            'course_id' => $payment->course_id,
            'bundle_id' => $payment->bundle_id
        ]);
    }

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
            PaymentStripe::handleWebhook($request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );

            Log::info('PaymentStripe::handleWebhook');
            Log::info(json_encode($request->all()));

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function handleUalaWebhook(Request $request)
    {
        try {
            // Validar firma del webhook de Ualá
            PaymentUala::handleWebhook($request->all());

            Log::info('PaymentUala::handleWebhook');
            Log::info(json_encode($request->all()));

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function checkout(Request $request)
    {
        try {
            $result = Payment::checkout($request->all());
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
} 
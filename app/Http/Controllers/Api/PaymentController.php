<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Bundle;
use App\Models\Payment;
use App\Models\Enrollment;
use Stripe\Stripe;

class PaymentController extends Controller
{
    public function createPaymentLink(Request $request)
    {
        $request->validate([
            'type' => 'required|in:course,bundle',
            'identifier' => 'required|string'
        ]);

        /* validar que el usuario sea un alumno registrado (si valido el email)  */
        $user = auth()->user();
        if (!$user->email_verified_at) {
            return response()->json([
                'error' => 'El usuario no es un alumno registrado'
            ], 401);
        }

        $enrollment = Enrollment::where('user_id', $user->id)
                                ->where('course_id', $request->identifier)
                                ->orWhere('bundle_id', $request->identifier)
                                ->first();

        if ($enrollment) {
            return response()->json([
                'error' => 'El usuario ya tiene una inscripción activa a este seminario o bundle'
            ], 401);
        }

        try {
            // Configurar Stripe
            Stripe::setApiKey(config('services.stripe.secret'));

            // Obtener el item (curso o bundle)
            $item = ($request->type === 'course' ? Course::class : Bundle::class)::where('identifier', $request->identifier)
                        ->where('is_active', true)
                        ->firstOrFail();

            $user = auth()->user();

            // Crear el link de pago
            $paymentLink = \Stripe\PaymentLink::create([
                'line_items' => [[
                    // TODO: Cambiar por el precio del item
                    'price' => $item->stripe_price_id,
                    'quantity' => 1,
                ]],
                'after_completion' => [
                    'type' => 'redirect',
                    'redirect' => [
                        'url' => config('app.frontend_url') . '/success',
                    ],
                ],
                'metadata' => [
                    'user_id' => $user->id,
                    'item_type' => $request->type,
                    'item_id' => $item->id,
                ],
                'customer_creation' => 'always',
            ]);

            return response()->json([
                'payment_link' => $paymentLink->url
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );

            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;
                
                // Crear registro de pago
                $payment = Payment::create([
                    'user_id' => $session->metadata->user_id,
                    'payment_id' => $session->id,
                    'provider' => 'stripe',
                    'status' => 'completed',
                    'amount' => $session->amount_total / 100,
                    'currency' => $session->currency,
                    'product_id' => $session->payment_link,
                    'metadata' => json_encode($session)
                ]);

                // Crear inscripción(es)
                if ($session->metadata->item_type === 'course') {
                    // Inscripción a curso individual
                    Enrollment::create([
                        'user_id' => $session->metadata->user_id,
                        'course_id' => $session->metadata->item_id,
                        'payment_id' => $payment->id
                    ]);
                } elseif ($session->metadata->item_type === 'bundle') {
                    // Inscripción a bundle (múltiples cursos)
                    $bundle = Bundle::with('courses')->find($session->metadata->item_id);
                    foreach ($bundle->courses as $course) {
                        Enrollment::create([
                            'user_id' => $session->metadata->user_id,
                            'course_id' => $course->id,
                            'payment_id' => $payment->id
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
} 
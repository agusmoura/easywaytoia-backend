<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Bundle;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Student;
use Stripe\Stripe;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    private $UALA_ACCESS_TOKEN;

    public function createPaymentLink(Request $request)
    {
   
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:course,bundle'],
            'identifier' => ['required', 'string'],
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

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
            $country = strtolower(Student::where('user_id', $user->id)->first()->country);
            if($country === 'argentina'){
                return $this->createUalaPaymentLink($request);
            } else {
                return $this->createStripePaymentLink($request);
            }

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

    private function handleUalaWebhook(Request $request)
    {
        Log::info('Uala Webhook received:', $request->all());

        try {
            $data = $request->all();
            
            // Validar la firma del webhook si Ualá lo proporciona
            // TODO: Implementar validación de firma cuando Ualá lo soporte

            // Buscar el pago por external_reference
            $payment = Payment::where('payment_id', $data['external_reference'])
                ->where('provider', 'uala')
                ->first();

            if (!$payment) {
                Log::error('Payment not found for external_reference: ' . $data['external_reference']);
                return response()->json(['error' => 'Payment not found'], 404);
            }

            // Actualizar el estado del pago según el evento
            switch ($data['status']) {
                case 'APPROVED':
                    $payment->status = 'completed';
                    $payment->save();

                    $metadata = json_decode($payment->metadata, true);

                    // Crear inscripción(es)
                    if ($metadata['item_type'] === 'course') {
                        Enrollment::create([
                            'user_id' => $payment->user_id,
                            'course_id' => $metadata['item_id'],
                            'payment_id' => $payment->id
                        ]);
                    } elseif ($metadata['item_type'] === 'bundle') {
                        $bundle = Bundle::with('courses')->find($metadata['item_id']);
                        foreach ($bundle->courses as $course) {
                            Enrollment::create([
                                'user_id' => $payment->user_id,
                                'course_id' => $course->id,
                                'payment_id' => $payment->id
                            ]);
                        }
                    }
                    break;

                case 'REJECTED':
                case 'CANCELLED':
                    $payment->status = 'failed';
                    $payment->save();
                    break;

                default:
                    Log::info('Unhandled Uala payment status: ' . $data['status']);
                    break;
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Error processing Uala webhook: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function createStripePaymentLink(Request $request){
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
    }

    private function createUalaPaymentLink(Request $request)
    {
        $this->getUalaAccessToken();
        $urlCheckout = config('services.uala.url_checkout');

        // Obtener el item (curso o bundle)
        $item = ($request->type === 'course' ? Course::class : Bundle::class)::where('identifier', $request->identifier)
            ->where('is_active', true)
            ->firstOrFail();

        $user = auth()->user();

        // Crear referencia única para el pago
        $external_reference = 'payment_' . uniqid() . '_' . $user->id;

        $data = [
            'amount' => $item->price,
            'description' => "Pago por {$request->type}: {$item->name}",
            'callback_fail' => config('app.frontend_url') . '/failed',
            'callback_success' => config('app.frontend_url') . '/success',
            'notification_url' => config('app.url') . '/api/webhooks/uala',
            'external_reference' => $external_reference,
            'metadata' => [
                'user_id' => $user->id,
                'item_type' => $request->type,
                'item_id' => $item->id
            ]
        ];

        Log::info('Payment data: ' . json_encode($data));

        $response = Http::withoutVerifying()
            ->withToken($this->UALA_ACCESS_TOKEN)
            ->post($urlCheckout, $data);

        Log::info(message: $response->json());

        if (!$response->successful()) {
            throw new \Exception('Error al crear el link de pago en Ualá');
        }

        // Guardar el pago pendiente
        Payment::create([
            'user_id' => $user->id,
            'payment_id' => $external_reference,
            'provider' => 'uala',
            'status' => 'pending',
            'amount' => $item->price,
            'currency' => 'ARS',
            'product_id' => $item->id,
            'metadata' => json_encode([
                'item_type' => $request->type,
                'item_id' => $item->id,
                'uala_order_id' => $response->json('id')
            ])
        ]);

        $paymentLink = $response->json('links.checkout_link');
        
        return response()->json([
            'payment_link' => $paymentLink
        ]);
    }

    private function getUalaAccessToken(){
        $urlToken = config('services.uala.url_token');

        $response = Http::withoutVerifying()->post($urlToken, [
            'username' => config('services.uala.username'),
            'client_id' => config('services.uala.client_id'),
            'client_secret_id' => config('services.uala.client_secret'),
            'grant_type' => 'client_credentials'
        ]);

        $this->UALA_ACCESS_TOKEN = $response->json('access_token');
    }
} 
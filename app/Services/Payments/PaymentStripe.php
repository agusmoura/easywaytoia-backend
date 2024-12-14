<?php

namespace App\Services\Payments;

use Stripe\Stripe;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PaymentStripe
{
    public static function createPaymentLink(array $data, $user)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $item = self::getItem($data);

        /* generar un id de pago unico para dentro de la plataforma */
        $paymentId = uniqid("eaia_");
        
        $payment = Payment::create([
            'user_id' => $user['id'],
            'payment_id' => $paymentId,
            'provider' => 'stripe',
            'status' => 'created',
            'product_id' => $item->id,
        ]);
        $payment->save();

        $paymentLink = \Stripe\PaymentLink::create([
            'line_items' => [[
                'price' => $item->stripe_price_id,
                'quantity' => 1,
            ]],
            'after_completion' => [
                'type' => 'redirect',
                'redirect' => [
                    'url' => $data['success_page'],
                ],
            ],
            'metadata' => [
                'payment_id' => $paymentId,
                'user_id' => $user['id'],
                'item_type' => $data['type'],
                'item_id' => $item->id,
            ],
            'customer_creation' => 'always',
        ]);

        $payment->buy_link = $paymentLink->url;
        $payment->save();

        return ['payment_link' => $paymentLink->url];
    }

    private static function getItem($data)
    {
        return ($data['type'] === 'course' ? Course::class : Bundle::class)::where('identifier', $data['identifier'])
            ->where('is_active', true)
            ->firstOrFail();
    }

    public static function handleWebhook($payload, $sigHeader, $endpointSecret)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        /* necesito loguear que evento viene y a que hora */
        Log::info('Event received', [
            'event_type' => $event['type'],
            'event_data' => $event['data']['object'],
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);
        
        switch ($event['type']) {
            case 'payment_link.created': //1
                // self::handlePaymentLinkCreated($event['data']['object']);
                break;
            case 'payment_intent.created': //2
            case 'payment_intent.succeeded': //3
                // self::handlePaymentIntentCreated($event['data']['object']);
                break;
            case 'checkout.session.completed': //4
                self::handleCheckoutSessionCompleted($event['data']['object']);
                break;
            case 'checkout.session.expired': //5
                self::handleCheckoutSessionExpired($event['data']['object']);
                break;
            
            default:
                Log::error('Unhandled event type:', [
                    'event_type' => $event['type'],
                    'event' => $event
                ]);
                return response()->json(['status' => 'Unhandled event type'], 200);
        }

        return response()->json(['status' => 'Event handled'], 200);
    }


    private static function handleCheckoutSessionCompleted($session)
    {
        $payment = Payment::where('payment_id', $session->metadata->payment_id)->first();

        if (!$payment) {
            return;
        }

        $payment->provider_payment_id = $session->id;
        $payment->amount = $session->amount_total / 100;
        $payment->currency = $session->currency;
        $payment->status = 'completed';
        $payment->metadata = json_encode($session->metadata);
        $payment->save();

        self::createEnrollments($session->metadata, $payment->provider_payment_id);
    }


 

    private static function handleCheckoutSessionExpired($session)
    {
        $payment = Payment::where('payment_id', $session->metadata->payment_id)->first();

        if (!$payment) {
            return;
        }

        $payment->status = 'expired';
        $payment->save();

        /* enviar mail de expiración */
        $user = User::find($payment->user_id);
        $user->notify(new \App\Notifications\PaymentExpiredNotification($payment));
    }

    private static function createEnrollments($metadata, $paymentId)
    {
        try {
            $payment = Payment::where('provider_payment_id', $paymentId)->first();
            $user = User::find($metadata->user_id);

            if (!$payment) {
                return;
            }

            if ($payment->status === 'completed') {
                return;
            }

            if ($metadata->item_type === 'course') {
                $existingEnrollment = Enrollment::where('user_id', $metadata->user_id)
                    ->where('course_id', $metadata->item_id)
                    ->where('payment_id', $paymentId)
                    ->first();

                if (!$existingEnrollment) {
                    $enrollment = Enrollment::create([
                        'user_id' => $metadata->user_id,
                        'course_id' => $metadata->item_id,
                        'payment_id' => $paymentId,
                        'status' => 'active',
                        'enrolled_at' => now()
                    ]);
                }
                
                $payment->save();
            } elseif ($metadata->item_type === 'bundle') {
                $bundle = Bundle::find($metadata->item_id);
                $courses = $bundle->getCourses();
                
                foreach ($courses as $course) {
                    // Check if enrollment already exists
                    $existingEnrollment = Enrollment::where('user_id', $metadata->user_id)
                        ->where('course_id', $course->id)
                        ->where('bundle_id', $bundle->id)
                        ->where('payment_id', $paymentId)
                        ->first();

                    if (!$existingEnrollment) {
                        $enrollment = Enrollment::create([
                            'user_id' => $metadata->user_id,
                            'course_id' => $course->id,
                            'bundle_id' => $bundle->id,
                            'payment_id' => $paymentId,
                            'status' => 'active',
                            'enrolled_at' => now()
                        ]);
                    }
                }
                
                $payment->bundle_id = $metadata->item_id;
                $payment->save();
            }


            $user->notify(new \App\Notifications\PurchaseConfirmationNotification($payment));
     
        } catch (\Exception $e) {
            Log::error('Error creating enrollments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 
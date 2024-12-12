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
                'user_id' => $user['id'],
                'item_type' => $data['type'],
                'item_id' => $item->id,
            ],
            'customer_creation' => 'always',
        ]);

        return ['payment_link' => $paymentLink->url];
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
        
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                self::handlePaymentIntentCreated($event['data']['object']);
                break;
            case 'checkout.session.completed':
                self::handleCheckoutSessionCompleted($event['data']['object']);
                break;
            case 'checkout.session.expired':
                self::handleCheckoutSessionExpired($event['data']['object']);
                break;
            case 'payment_link.created':
            case 'payment_intent.created':
                break;
            default:
                Log::error('Unhandled event type:', [
                    'event_type' => $event['type'],
                    'event' => $event
                ]);
                return response()->json(['status' => 'Unhandled event type'], 200);
        }
    }

    private static function handlePaymentIntentSucceeded($paymentIntent)
    {
        // Lógica para manejar el pago exitoso
    }

    private static function handleCheckoutSessionCompleted($session)
    {
        $payment = Payment::create([
            'user_id' => $session->metadata->user_id,
            'payment_id' => $session->id,
            'provider' => 'stripe',
            'status' => 'completed',
            'amount' => $session->amount_total / 100,
            'currency' => $session->currency,
            'product_id' => $session->id,
            'metadata' => json_encode($session->metadata)
        ]);

        self::createEnrollments($session->metadata, $payment->id);
    }

    private static function handlePaymentIntentCreated($paymentIntent)
    {
        // Lógica para manejar la creación del payment intent
    }

    private static function getItem($data)
    {
        return ($data['type'] === 'course' ? Course::class : Bundle::class)::where('identifier', $data['identifier'])
            ->where('is_active', true)
            ->firstOrFail();
    }

    private static function handleCheckoutSessionExpired($session)
    {
        $payment = Payment::where('payment_id', $session->id)->first();
        $payment->status = 'expired';
        $payment->save();

        /* enviar mail de expiración */
        $user = User::find($payment->user_id);
        $user->notify(new \App\Notifications\PaymentExpiredNotification($payment));
    }

    private static function createEnrollments($metadata, $paymentId)
    {
        try {

            Log::info('Starting createEnrollments', [
                'metadata' => $metadata,
                'paymentId' => $paymentId
        ]);

        $payment = Payment::find($paymentId);
        Log::info('Payment found', ['payment' => $payment]);

        $user = User::find($metadata->user_id);
        Log::info('User lookup result', [
            'user_id' => $metadata->user_id,
            'user_found' => $user ? true : false,
            'user_email' => $user ? $user->email : null
        ]);

        if ($metadata->item_type === 'course') {
            Log::info('Processing course enrollment', [
                'course_id' => $metadata->item_id
            ]);

            // Check if enrollment already exists
            $existingEnrollment = Enrollment::where('user_id', $metadata->user_id)
                ->where('course_id', $metadata->item_id)
                ->where('payment_id', $paymentId)
                ->first();

            Log::info('Existing enrollment check', [
                'exists' => $existingEnrollment ? true : false
            ]);

            if (!$existingEnrollment) {
                $enrollment = Enrollment::create([
                    'user_id' => $metadata->user_id,
                    'course_id' => $metadata->item_id,
                    'payment_id' => $paymentId,
                    'status' => 'active',
                    'enrolled_at' => now()
                ]);
                Log::info('New enrollment created', ['enrollment' => $enrollment]);
            }
            
            $payment->course_id = $metadata->item_id;
            $payment->save();
        } elseif ($metadata->item_type === 'bundle') {
            Log::info('Processing bundle enrollment', [
                'bundle_id' => $metadata->item_id
            ]);

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
                    Log::info('New bundle course enrollment created', ['enrollment' => $enrollment]);
                }
            }
            
            $payment->bundle_id = $metadata->item_id;
            $payment->save();
        }

        /* enviar mail de confirmación */
        Log::info('Sending purchase confirmation notification', [
            'payment' => $payment
        ]);

        if ($user) {
            Log::info('Attempting to send purchase confirmation notification', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            try {
                $user->notify(new \App\Notifications\PurchaseConfirmationNotification($payment));
                Log::info('Purchase confirmation notification sent successfully');
            } catch (\Exception $e) {
                Log::error('Failed to send purchase confirmation notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::error('User not found for purchase confirmation notification', [
                    'user_id' => $metadata->user_id,
                    'payment_id' => $paymentId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error creating enrollments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 
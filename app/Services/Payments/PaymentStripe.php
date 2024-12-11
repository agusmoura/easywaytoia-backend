<?php

namespace App\Services\Payments;

use Stripe\Stripe;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Bundle;
use App\Models\Course;
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
            case 'payment_link.created':
            case 'payment_intent.created':
                break;
            default:
                Log::info('Unhandled event type:', ['event_type' => $event->type]);
                Log::info('Event:', ['event' => $event]);
                return response()->json(['status' => 'Unhandled event type'], 200);
        }

    }

    private static function handlePaymentIntentSucceeded($paymentIntent)
    {
        // Lógica para manejar el pago exitoso
    }

    private static function handleCheckoutSessionCompleted($session)
    {
        Log::info('session:', ['session' => $session]);
        
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

    private static function createEnrollments($metadata, $paymentId)
    {
        Log::info('PaymentStripe::createEnrollments - Start');

        $itemType = $metadata->item_type;
        $itemId = $metadata->item_id;
        $userId = $metadata->user_id;

        Log::info('Item type:', ['item_type' => $itemType]);
        Log::info('Item ID:', ['item_id' => $itemId]);
        Log::info('User ID:', ['user_id' => $userId]);
        
        if ($itemType === 'course') {
            Enrollment::create([
                'user_id' => $userId,
                'course_id' => $itemId,
                'payment_id' => $paymentId
            ]);
        } elseif ($itemType === 'bundle') {
            $bundle = Bundle::find($itemId);
            $courses = $bundle->getCourses();
            foreach ($courses as $course) {
                Enrollment::create([
                    'user_id' => $userId,
                    'course_id' => $course->id,
                    'bundle_id' => $bundle->id,
                    'payment_id' => $paymentId
                ]);
            }
        }
    }
} 
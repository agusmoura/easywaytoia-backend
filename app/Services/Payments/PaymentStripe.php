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
    
        $event = \Stripe\Webhook::constructEvent(
            $payload, $sigHeader, $endpointSecret
        );
        Log::info('Event type:', ['type' => $event->type]);
        
        
        try {
            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;
                
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

                self::createEnrollments($session->metadata, $payment->id);
            } 
            else if ($event->type === 'payment_intent.created') {
                $paymentLink = $event->data->object;
                
                if (empty($paymentLink->metadata) || !isset($paymentLink->metadata->user_id)) {
                    Log::warning('Payment intent created without user_id in metadata', [
                        'payment_intent_id' => $paymentLink->id
                    ]);
                    return true;
                }
                
                $payment = Payment::create([
                    'user_id' => $paymentLink->metadata->user_id,
                    'payment_id' => $paymentLink->id,
                    'provider' => 'stripe',
                    'status' => 'pending',
                    'amount' => $paymentLink->amount_total / 100,
                    'currency' => $paymentLink->currency,
                    'product_id' => $paymentLink->id,
                    'metadata' => json_encode($paymentLink)
                ]);
            } 
            else if ($event->type === 'payment_intent.succeeded') {
                $paymentIntent = $event->data->object;
                $payment = Payment::where('payment_id', $paymentIntent->id)->first();
                
                if (!$payment) {
                    Log::error('Payment not found for payment_intent: ' . $paymentIntent->id);
                    // Create payment record if it doesn't exist
                    $payment = Payment::create([
                        'user_id' => $paymentIntent->metadata->user_id,
                        'payment_id' => $paymentIntent->id,
                        'provider' => 'stripe',
                        'status' => 'completed',
                        'amount' => $paymentIntent->amount / 100,
                        'currency' => $paymentIntent->currency,
                        'product_id' => $paymentIntent->id,
                        'metadata' => json_encode($paymentIntent->metadata)
                    ]);
                } else {
                    $payment->update(['status' => 'completed']);
                }
                
                self::createEnrollments($paymentIntent->metadata, $payment->id);
            } 
            else {
                Log::error('PaymentStripe::handleWebhook - Evento no reconocido: ' . $event->type);
            }

            Log::info('PaymentStripe::handleWebhook - Success');
            return true;
        } catch (\Exception $e) {
            Log::error('PaymentStripe::handleWebhook - Error', [
                'message' => $e->getMessage(),
                'event_type' => $event->type
            ]);
            throw $e;
        }
    }

    private static function getItem($data)
    {
        return ($data['type'] === 'course' ? Course::class : Bundle::class)::where('identifier', $data['identifier'])
            ->where('is_active', true)
            ->firstOrFail();
    }

    private static function createEnrollments($metadata, $paymentId)
    {
        if ($metadata['item_type'] === 'course') {
            Enrollment::create([
                'user_id' => $metadata['user_id'],
                'course_id' => $metadata['item_id'],
                'payment_id' => $paymentId
            ]);
        } elseif ($metadata['item_type'] === 'bundle') {
            $bundle = Bundle::with('courses')->find($metadata['item_id']);
            foreach ($bundle->courses as $course) {
                Enrollment::create([
                    'user_id' => $metadata['user_id'],
                    'course_id' => $course->id,
                    'payment_id' => $paymentId
                ]);
            }
        }
    }
} 
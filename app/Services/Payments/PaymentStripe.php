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
        // Set Stripe API key first
        Stripe::setApiKey(config('services.stripe.secret'));

        $event = \Stripe\Webhook::constructEvent(
            $payload, $sigHeader, $endpointSecret
        );
      
        
        try {
            if ($event->type === 'payment_intent.succeeded') {
                $paymentIntent = $event->data->object;
                
                // Try to find payment by payment intent ID
                $payment = Payment::where('payment_id', $paymentIntent->id)
                    ->orWhere('product_id', $paymentIntent->id)
                    ->first();
                
                if (!$payment) {
                    // If payment not found, try to get metadata from the payment intent
                    $metadata = $paymentIntent->metadata;
                    Log::info('Metadata:', ['metadata' => $metadata]);
                    
                    if (empty($metadata) || empty($metadata->user_id)) {
                        Log::error('Missing user_id in payment intent metadata', [
                            'payment_intent_id' => $paymentIntent->id,
                            'metadata' => $metadata
                        ]);
                        return true;
                    }


                    $payment = Payment::create([
                        'user_id' => $metadata->user_id,
                        'payment_id' => $paymentIntent->id,
                        'provider' => 'stripe',
                        'status' => 'completed',
                        'amount' => $paymentIntent->amount / 100,
                        'currency' => $paymentIntent->currency,
                        'product_id' => $paymentIntent->id,
                        'metadata' => json_encode($metadata)
                    ]);

                    self::createEnrollments($metadata, $payment->id);
                } else {
                    $payment->update(['status' => 'completed']);
                }
            }

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
        Log::info('PaymentStripe::createEnrollments - Start');
        Log::info('Metadata:', ['metadata' => $metadata]);
        Log::info('Payment ID:', ['payment_id' => $paymentId]);
     

        $itemType = $metadata->Stripe->StripeObject->item_type;
        $itemId = $metadata->Stripe->StripeObject->item_id;
        $userId = $metadata->Stripe->StripeObject->user_id;

        Log::info('Item type:', ['item_type' => $itemType]);
        Log::info('Item ID:', ['item_id' => $itemId]);
        Log::info('User ID:', ['user_id' => $userId]);
        
        // if ($metadata->item_type === 'course') {
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
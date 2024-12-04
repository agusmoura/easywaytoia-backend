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

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            
            $payment = Payment::create([
                'user_id' => $session->metadata->user_id,
                'payment_id' => $session->id,
                'provider' => 'stripe',
                'status' => 'completed',
                'amount' => $session->amount_total,
                'currency' => $session->currency,
                'product_id' => $session->payment_link,
                'metadata' => json_encode($session)
            ]);

            self::createEnrollments($session->metadata, $payment->id);
        }

        return true;
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
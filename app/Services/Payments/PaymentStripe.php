<?php

namespace App\Services\Payments;

use Stripe\Stripe;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PaymentStripe
{

    /*----MAIN FUNCTIONS----*/
    
    public static function createPaymentLink(Product $product, $user)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $payment = Payment::create([
            'user_id' => $user['id'],
            'payment_id' => uniqid("eaia_"),
            'provider' => 'stripe',
            'status' => 'created',
            'product_id' => $product['id'],
        ]);
        $payment->save();

        $paymentLink = \Stripe\PaymentLink::create([
            'line_items' => [[
                'price' => $product['stripe_price_id'],
                'quantity' => 1,
            ]],
            'after_completion' => [
                'type' => 'redirect',
                'redirect' => [
                    'url' => $product['success_page'],
                ],
            ],
            
            'metadata' => [
                'payment_id' => $payment->payment_id,
                'user_id' => $user['id'],
                'item_type' => $product['type'],
                'item_id' => $product['id'],
            ],
            'customer_creation' => 'always',
        ]);

        $payment->buy_link = $paymentLink->url;
        $payment->save();

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
            case 'payment_intent.payment_failed': //5
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
        $payment->status = 'success';
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

        $payment->status = 'failed';
        $payment->save();

        /* enviar mail de expiración */
        $user = User::find($payment->user_id);
        $user->notify(new \App\Notifications\PaymentExpiredNotification($payment));
    }

    private static function createEnrollments($metadata, $paymentId)
    {
        try {
            $payment = self::getPayment($paymentId);
            $user = User::find($metadata->user_id);

            if (!$payment || $payment->status === 'completed') {
                return;
            }

            self::logPaymentDetails($payment, $metadata, $paymentId);

            if (self::hasExistingEnrollment($metadata, $payment)) {
                return;
            }

            self::createNewEnrollment($metadata, $payment);
            self::completePayment($payment);
            self::notifyUser($user, $payment);

        } catch (\Exception $e) {
            self::logError($e);
        }
    }

    private static function getPayment($paymentId)
    {
        return Payment::where('provider_payment_id', $paymentId)->first();
    }

    private static function logPaymentDetails($payment, $metadata, $paymentId)
    {
        Log::info('Payment', [
            'payment' => $payment,
            'metadata' => $metadata,
            'paymentId' => $paymentId
        ]);
    }

    private static function hasExistingEnrollment($metadata, $payment)
    {
        return Enrollment::where('user_id', $metadata->user_id)
            ->where('product_id', $metadata->item_id)
            ->where('payment_id', $payment->id)
            ->exists();
    }

    private static function createNewEnrollment($metadata, $payment)
    {
        Enrollment::create([
            'user_id' => $metadata->user_id,
            'product_id' => $metadata->item_id,
            'payment_id' => $payment->id,
            'status' => 'active',
            'enrolled_at' => now()
        ]);
    }

    private static function completePayment($payment)
    {
        $payment->status = 'completed';
        $payment->save();
    }

    private static function notifyUser($user, $payment)
    {
        $user->notify(new \App\Notifications\PurchaseConfirmationNotification($payment));
    }

    private static function logError($exception)
    {
        Log::error('Error creating enrollments', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
} 
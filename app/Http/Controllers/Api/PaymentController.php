<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function createStripeCheckout(Request $request)
    {
        $request->validate([
            'product_id' => 'required|string',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url'
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $request->product_id,
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $request->success_url,
                'cancel_url' => $request->cancel_url,
                'client_reference_id' => auth()->id()
            ]);

            Payment::create([
                'user_id' => auth()->id(),
                'payment_id' => $session->id,
                'provider' => 'stripe',
                'status' => 'pending',
                'amount' => $session->amount_total / 100,
                'currency' => $session->currency,
                'product_id' => $request->product_id,
                'metadata' => json_encode($session)
            ]);

            return response()->json([
                'url' => $session->url
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
                
                $payment = Payment::where('payment_id', $session->id)->first();
                if ($payment) {
                    $payment->update([
                        'status' => 'completed',
                        'metadata' => json_encode($session)
                    ]);
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
<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\User;

class PaymentUala
{
    private static $accessToken;

    /* **************************** */
    public static function createPaymentLink(Product $product, $user)	
    {
        try {
            $paymentId = uniqid("eaia_");
            self::getToken();
            $order = self::createOrder(
                $product['price'],
                "Compra de {$product['name']}",
                config('app.prod_frontend_url') . '/failed?uid=' . $paymentId,
                $product['success_page'],
                config('app.prod_url') . '/api/webhooks/uala'
            );
            $payment = Payment::create([
                'user_id' => $user['id'],
                'payment_id' => $paymentId,
                'provider_payment_id' => $order['uuid'],
                'provider' => 'uala',
                'status' => 'pending',
                'amount' => $order['amount'],
                'currency' => "ARS",
                'product_id' => $product['id'],
                'metadata' => json_encode([
                    'payment_id' => $paymentId,
                    'user_id' => $user['id'],
                    'item_type' => $product['type'],
                    'item_id' => $product['id']
                ])
            ]);
            $payment->buy_link = $order['links']['checkout_link'];
            $payment->save();

            return ['payment_link' => $payment->buy_link];
        } catch (\Exception $e) {
            Log::error('Error creating payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Error al inicializar el servicio de pago', 500);
        }
    }

    public static function handleWebhook(array $data)
    {
        Log::info('Uala Webhook received:', $data);

        $payment = Payment::where('provider_payment_id', $data['uuid'])
            ->where('provider', 'uala')
            ->first();

        if (!$payment) {
            Log::error('Payment not found for external_reference: ' . $data['external_reference']);
            throw new \Exception('Payment not found', 404);
        }

        switch ($data['status']) {
            case 'APPROVED':
                self::handlePaymentApproved($payment);
                break;
            case 'REJECTED':
            case 'CANCELLED':
                self::handlePaymentFailed($payment);
                break;

            default:
                Log::info('Unhandled Uala payment status: ' . $data['status']);
                break;
        }

        return response()->json(['status' => 'Event handled'], 200);

    }

    private static function handlePaymentApproved($payment)
    {
        $payment->status = 'success';
        $payment->save();

        $metadata = json_decode($payment->metadata);
        self::createEnrollments($metadata, $payment->id);

        return response()->json(['status' => 'Event handled'], 200);
    }

    private static function handlePaymentFailed($payment)
    {
        try {
            $payment->status = 'failed';
            $payment->save();

            $user = User::find($payment->user_id);
            if ($user) {
                $user->notify(new \App\Notifications\PaymentExpiredNotification($payment));
            }

            return response()->json(['status' => 'Event handled'], 200);
        } catch (\Exception $e) {
            Log::error('Error handling failed payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment' => $payment
            ]);
            return response()->json(['status' => 'Error handling event'], 500);
        }
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


    private static function getToken()
    {
        $url= "https://auth.developers.ar.ua.la/v2/api/auth/token";
        $username = config('services.uala.username');
        $clientId = config('services.uala.client_id');
        $clientSecret = config('services.uala.client_secret');

        try {
            $response = Http::withoutVerifying()
                ->post($url, [
                    'username' => $username,
                    'client_id' => $clientId,
                    'client_secret_id' => $clientSecret,
                    'grant_type' => 'client_credentials'
                ]);

            if (!$response->successful()) {
                Log::error('Error response from Uala', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Error en la respuesta de Ualá');
            }

            $data = $response->json();
            if (!isset($data['access_token'])) {
                throw new \Exception('Token no encontrado en la respuesta');
            }

            self::$accessToken = $data['access_token'];
            
        } catch (\Exception $e) {
            Log::error('Error getting token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private static function createOrder(
        $amount,
        $description,
        $callback_fail,
        $callback_success,
        $notification_url,
    )
    {
        if (!self::$accessToken) {
            self::getToken();
        }

        $url = "https://checkout.developers.ar.ua.la/v2/api/checkout";

        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => 'Bearer ' . self::$accessToken,
            'Content-Type' => 'application/json'
        ])->post($url, [
            "amount" => $amount,
            "description" => $description,
            "callback_fail" => $callback_fail,
            "callback_success" => $callback_success,
            "notification_url" => $notification_url,
            "external_reference" => "external_reference"
        ]);

        return $response->json();
    }

} 
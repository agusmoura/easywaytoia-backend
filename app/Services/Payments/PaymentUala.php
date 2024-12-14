<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\User;
use Uala\SDK;

class PaymentUala
{
    private $UALA_ACCESS_TOKEN;
    
    public static function createPaymentLink(array $data, $user)	
    {
        try {
            $username = config('services.uala.username');
            $clientId = config('services.uala.client_id');
            $clientSecret = config('services.uala.client_secret');

        $sdk = new SDK($username, $clientId, $clientSecret, false);
        $item = self::getItem($data);
        $paymentId = uniqid("eaia_");

        $order = $sdk->createOrder(
            $item->price,
            "Compra de {$item->name}",
            config('app.prod_frontend_url') . '/failed?payment_id=' . $paymentId,
            $item->success_page,
            config('app.prod_url') . '/api/webhooks/uala'
        );

        Log::info('Generated order', [
            'generatedOrder' => $order,
            'uuid' => $order->uuid,
            'link' => $order->links->checkoutLink
        ]);


        $payment = Payment::create([
            'user_id' => $user['id'],
            'payment_id' => $paymentId,
            'provider_payment_id' => $order->uuid,
            'provider' => 'uala',
            'status' => 'pending',
            'amount' => $order->amount,
            'currency' => "ARS",
            'product_id' => $item->id,
            'metadata' => json_encode([
                'payment_id' => $paymentId,
                'user_id' => $user['id'],
                'item_type' => $data['type'],
                'item_id' => $item->id
            ])
        ]);



        $payment->buy_link = $order->links->checkoutLink;
        $payment->save();

        return ['payment_link' => $order->links->checkoutLink];
        } catch (\Exception $e) {
            Log::error('Error creating payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public static function handleWebhook(array $data)
    {
        Log::info('Uala Webhook received:', $data);

        $payment = Payment::where('payment_id', $data['external_reference'])
            ->where('provider', 'uala')
            ->first();

        if (!$payment) {
            Log::error('Payment not found for external_reference: ' . $data['external_reference']);
            throw new \Exception('Payment not found', 404);
        }

        switch ($data['status']) {
            case 'APPROVED':
                self::handlePaymentApproved($data);
                break;
            case 'REJECTED':
            case 'CANCELLED':
                self::handlePaymentFailed($data);
                break;

            default:
                Log::info('Unhandled Uala payment status: ' . $data['status']);
                break;
        }

        return true;
    }

    private static function handlePaymentApproved($data)
    {
        $payment = Payment::where('provider_payment_id', $data['uuid'])->first();

        if (!$payment) {
            Log::error('Payment not found for external_reference: ' . $data['external_reference']);
            throw new \Exception('Payment not found', 404);
        }

        Log::info('Payment found', [
            'payment' => $payment,
            'data' => $data
        ]);

        $payment->status = 'success';
        $payment->save();

        $metadata = json_decode($payment->metadata);
        self::createEnrollments($metadata, $payment->id);
    }

    private static function handlePaymentFailed($payment)
    {
        $payment->status = 'failed';
        $payment->save();

        $user = User::find($payment->user_id);
        $user->notify(new \App\Notifications\PaymentExpiredNotification($payment));
    }

    private static function getItem($data)
    {
        return ($data['type'] === 'course' ? Course::class : Bundle::class)::where('identifier', $data['identifier'])
            ->where('is_active', true)
            ->firstOrFail();
    }

    private static function createEnrollments($metadata, $paymentId)
    {
        try {
            $payment = Payment::find($paymentId);
            $user = User::find($metadata->user_id);

            Log::info('Payment', [
                'payment' => $payment,
                'metadata' => $metadata,
                'paymentId' => $paymentId
            ]);

            if ($payment->status === 'completed') {
                return;
            }

            if ($metadata->item_type === 'course') {
                $existingEnrollment = Enrollment::where('user_id', $metadata->user_id)
                    ->where('course_id', $metadata->item_id)
                    ->where('payment_id', $payment->id)
                    ->first();

                if (!$existingEnrollment) {
                    $enrollment = Enrollment::create([
                        'user_id' => $metadata->user_id,
                        'course_id' => $metadata->item_id,
                        'payment_id' => $payment->id,
                        'status' => 'active',
                        'enrolled_at' => now()
                    ]);
                }
                
                $payment->status = 'completed';
                $payment->save();
            } elseif ($metadata->item_type === 'bundle') {
                $bundle = Bundle::find($metadata->item_id);
                $courses = $bundle->getCourses();
                
                foreach ($courses as $course) {
                    $existingEnrollment = Enrollment::where('user_id', $metadata->user_id)
                        ->where('course_id', $course->id)
                        ->where('bundle_id', $bundle->id)
                        ->where('payment_id', $payment->id)
                        ->first();

                    if (!$existingEnrollment) {
                        $enrollment = Enrollment::create([
                            'user_id' => $metadata->user_id,
                            'course_id' => $course->id,
                            'bundle_id' => $bundle->id,
                            'payment_id' => $payment->id,
                            'status' => 'active',
                            'enrolled_at' => now()
                        ]);
                    }
                }
                
                $payment->bundle_id = $metadata->item_id;
                $payment->status = 'completed';
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

    private function getUalaAccessToken()
    {
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
<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Bundle;
use App\Models\Course;

class PaymentUala
{
    private $UALA_ACCESS_TOKEN;
    public static function createPaymentLink(array $data, $user)
    {
        $paymentUala = new PaymentUala();
        $paymentUala->getUalaAccessToken();

        $item = self::getItem($data);
        $external_reference = 'payment_' . uniqid() . '_' . $user->id;

        $paymentData = [
            'amount' => $item->price,
            'description' => "Pago por {$data['type']}: {$item->name}",
            'callback_fail' => config('app.frontend_url') . '/failed',
            'callback_success' => config('app.frontend_url') . '/success',
            'notification_url' => config('app.frontend_url') . '/api/webhooks/uala',
            'external_reference' => $external_reference,
            'metadata' => [
                'user_id' => $user->id,
                'item_type' => $data['type'],
                'item_id' => $item->id
            ]
        ];

        Log::info('Payment data:', $paymentData);

        $response = Http::withoutVerifying()
            ->withToken($paymentUala->UALA_ACCESS_TOKEN)
            ->post(config('services.uala.url_checkout'), $paymentData);

        Log::info('Uala response:', $response->json());

        if (!$response->successful()) {
            throw new \Exception('Error al crear el link de pago en Ualá');
        }

        Payment::create([
            'user_id' => $user->id,
            'payment_id' => $external_reference,
            'provider' => 'uala',
            'status' => 'pending',
            'amount' => $item->price,
            'product_id' => $item->id,
            'currency' => 'ARS',
            'metadata' => json_encode($paymentData['metadata'])
        ]);


        return ['payment_link' => $response['links']['checkout_link']];
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
                $payment->status = 'completed';
                $payment->save();
                self::createEnrollments(json_decode($payment->metadata, true), $payment->id);
                break;

            case 'REJECTED':
            case 'CANCELLED':
                $payment->status = 'failed';
                $payment->save();
                break;

            default:
                Log::info('Unhandled Uala payment status: ' . $data['status']);
                break;
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

    private function getUalaAccessToken(){
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
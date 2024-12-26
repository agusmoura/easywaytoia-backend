<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Enrollment;

class VerificationController extends Controller
{
    public function sendVerificationEmail(Request $request)
    {

        if (!$request->user()) {
            return response()->json([
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El email ya está verificado'
            ], 200);
        } 

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $request->user()->getKey(),
                'hash' => sha1(string: $request->user()->getEmailForVerification()),
            ]
        );

        // Para desarrollo, devolvemos la URL en la respuesta
        return response()->json([
            'message' => 'Link de verificación generado',
            'verification_url' => $verificationUrl
        ], 200);
    }

    public function verify(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return redirect(config('app.prod_frontend_url') . '/login?verification=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.prod_frontend_url') . '/login?verification=already');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            // Create free enrollment for LM course
            $lmCourse = Product::where('identifier', 'lm')->firstOrFail();
            if ($lmCourse) {
                // Create a free payment record
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'payment_id' => uniqid('free_lm_'),
                    'provider' => 'system',
                    'status' => 'completed',
                    'amount' => 0,
                    'currency' => 'ARS',
                    'product_id' => $lmCourse->id,
                    'metadata' => json_encode([
                        'payment_id' => uniqid('free_lm_'),
                        'user_id' => $user->id,
                        'item_type' => 'course',
                        'item_id' => $lmCourse->id
                    ])
                ]);

                // Create the enrollment
                Enrollment::create([
                    'user_id' => $user->id,
                    'product_id' => $lmCourse->id,
                    'payment_id' => $payment->id
                ]);
            }
        }

        return redirect(config('app.prod_frontend_url') . '/login?verification=success');
    }
} 
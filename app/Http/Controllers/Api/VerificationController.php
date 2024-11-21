<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    public function sendVerificationEmail(Request $request)
    {
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
                'hash' => sha1($request->user()->getEmailForVerification()),
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
        $user = \App\Models\User::findOrFail($id);

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'URL de verificación inválida'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El email ya está verificado'
            ], 200);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verificado exitosamente'
        ], 200);
    }
} 
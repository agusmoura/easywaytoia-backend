<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\UserDevice;

class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            // Verificar si el token corresponde al dispositivo actual
            $deviceId = $request->header('Device-ID');
            if (!$deviceId) {
                return response()->json(['error' => 'Device-ID no proporcionado'], 401);
            }

            $device = UserDevice::where('device_id', $deviceId)
                              ->where('user_id', $user->id)
                              ->first();

            if (!$device || $device->token !== JWTAuth::getToken()->get()) {
                return response()->json(['error' => 'Sesión no válida para este dispositivo'], 401);
            }

            // Actualizar última actividad
            $device->update(['last_activity' => now()]);

        } catch (JWTException $e) {
            return response()->json(['error' => 'Token invalido'], 401);
        }

        return $next($request);
    }
}
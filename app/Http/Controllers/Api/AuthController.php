<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Alumno;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\UserDevice;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'username' => ['required', 'string', 'max:255', Rule::unique('users')],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
                'password' => ['required', 'string', 'min:6', 'confirmed'],
                'nombre' => ['required', 'string', 'max:255'],
                'apellido' => ['required', 'string', 'max:255'],
                'pais' => ['required', 'string', 'max:255'],
                'telefono' => ['required', 'string', 'max:255'],
                'direccion' => ['required', 'string', 'max:255'],
            ]);

            if($validator->fails()){
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            if(!$user){
                throw new \Exception('Error al registrar el usuario');
            }

            // Generar URL de verificación
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            // Enviar email de verificación
            $user->sendEmailVerificationNotification();

            Alumno::create([
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'telefono' => $request->telefono,
                'pais' => $request->pais,
                'direccion' => $request->direccion,
                'user_id' => $user->id,
            ]);

            $token = JWTAuth::fromUser($user);

            return response()->json(
                data: ['message' => 'Alumno registrado exitosamente','verification_url' => $verificationUrl],
                status: 201
            );
        } catch (\Exception $e) {
            $code = (int) $e->getCode() ?? 500;
            $message = 'Error al registrar el alumno';
            $error = $e->getMessage();
            if(json_decode($error) && json_last_error() === JSON_ERROR_NONE){
                $error = json_decode($error, true);
            }
            return response()->json(data: ['message' => $message, 'error' => $error], status: $code);
        }
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'password' => 'required|string',
                'identifier' => 'required|string',
                'device_id' => 'required|string'
            ]);

            $loginField = filter_var($credentials['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $loginCredentials = [
                $loginField => $credentials['identifier'],
                'password' => $credentials['password']
            ];

            if (!$token = JWTAuth::attempt($loginCredentials)) {
                throw new \Exception('Credenciales invalidas', 401);
            }

            $user = auth()->user();

            if (!$user->email_verified_at) {
                return response()->json([
                    'error' => 'El usuario no es un alumno registrado, por favor verifique su email'
                ], 401);
            }
            
            $deviceCount = UserDevice::where('user_id', $user->id)->count();
            if ($deviceCount >= 3) {
                UserDevice::where('user_id', $user->id)
                         ->orderBy('last_activity', 'asc')
                         ->first()
                         ->delete();
            }

            UserDevice::updateOrCreate(
                ['device_id' => $request->device_id],
                [
                    'user_id' => $user->id,
                    'token' => $token,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'last_activity' => now()
                ]
            );

            return response()->json([
                'token' => $token,
                'type' => 'Bearer'
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $code = (int) $e->getCode();
            $statusCode = ($code >= 100 && $code <= 599) ? $code : 500;
            $message = 'Error al iniciar sesión';
            $error = $e->getMessage();
            return response()->json(['message' => $message, 'error' => $error], $statusCode);
        }
    }

    public function logout(Request $request)
    {
        try {
            $deviceId = $request->header('Device-ID');
            $user = auth()->user();

            // Eliminar el dispositivo
            UserDevice::where('device_id', $deviceId)
                     ->where('user_id', $user->id)
                     ->delete();

            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Sesión cerrada exitosamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al cerrar sesión'], 500);
        }
    }

    public function test(Request $request)
    {
        return response()->json(['message' => 'Token valido'], 200);
    }
}

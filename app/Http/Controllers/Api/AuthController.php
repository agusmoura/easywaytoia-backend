<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Validator;
use App\Models\Student;
use App\Models\UserDevice;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $result = User::registerUser($request->all());
            
            return response()->json([
                'message' => 'Usuario registrado exitosamente, se ha enviado un correo para verificar el email',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $code = (int) $e->getCode() ?? 500;
            $message = 'Error al registrar el alumno';
            $error = $e->getMessage();
            if(json_decode($error) && json_last_error() === JSON_ERROR_NONE){
                $error = json_decode($error, true);
            }
            return response()->json(['message' => $message, 'error' => $error], $code);
        }
    }

    public function login(Request $request)
    {
        try {
            $result = User::loginUser($request->all());
            
            return response()->json([
                'token' => $result['token'],
                'device_id' => $result['device_id'],
                'userData' => $result['userData'],
                'type' => 'Bearer'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $code = (int) $e->getCode();
            $statusCode = ($code >= 100 && $code <= 599) ? $code : 500;
            return response()->json([
                'message' => 'Error al iniciar sesión',
                'error' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function logout(Request $request)
    {
        try {
            User::logoutUser($request->header('Device-ID'));
            return response()->json(['message' => 'Sesión cerrada exitosamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al cerrar sesión'], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $result = User::forgotPassword($request->all());
            return response()->json($result, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en forgotPassword: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $content = $request->getContent();
            $data = json_decode($content, true);
            $result = User::resetPassword($data);
            return response()->json($result, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en resetPassword: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al restablecer la contraseña',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function myAccount(Request $request)
    {
        try {
            $result = User::getAccountInfo(auth()->id());
            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Error en myAccount: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener la información de la cuenta',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
}

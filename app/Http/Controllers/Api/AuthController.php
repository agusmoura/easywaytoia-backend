<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;

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
            $token = User::loginUser($request->all());
            
            return response()->json([
                'token' => $token,
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
}

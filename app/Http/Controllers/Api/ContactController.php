<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function getWhatsAppLink(Request $request)
    {
        try {
            $user = auth()->user();
            $student = $user->student;

            if (!$student || !$student->phone) {
                return response()->json([
                    'message' => 'No se encontró un número de teléfono registrado'
                ], 404);
            }

            // Format phone number (remove spaces, dashes, etc)
            $phone = preg_replace('/[^0-9]/', '', $student->phone);
            
            // Add default country code if not present
            if (!str_starts_with($phone, '+')) {
                $phone = '+' . $phone;
            }

            $message = "¡Hola! Soy {$student->name} {$student->last_name}, estudiante de EasyWay2IA.";
            $message .= "\n\nSoy de {$student->country} y quiero obtener el Seminario gratuito de IA";

            
            // Create WhatsApp link
            $whatsappLink = "https://wa.me/{$phone}?text=" . urlencode($message);

            return response()->json([
                'whatsapp_link' => $whatsappLink
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar el link de WhatsApp',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('Bienvenido - Verifica tu dirección de email')
            ->greeting('¡Bienvenido a nuestra plataforma!')
            ->line('Estamos emocionados de que te hayas unido a nosotros.')
            ->line('Para comenzar, necesitamos que verifiques tu dirección de email.')
            ->action('Verificar Email', $url)
            ->line('Si no creaste una cuenta, puedes ignorar este email.')
            ->salutation('¡Saludos!');
    }
} 
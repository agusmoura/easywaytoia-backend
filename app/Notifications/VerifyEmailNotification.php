<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('Verificar dirección de email')
            ->line('Por favor haz click en el botón para verificar tu dirección de email.')
            ->action('Verificar Email', $url)
            ->line('Si no creaste una cuenta, puedes ignorar este email.');
    }
} 
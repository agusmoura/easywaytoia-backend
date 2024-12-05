<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('¡Bienvenido a EasyWay2IA! 🎉')
            ->greeting('¡Hola! 👋')
            ->line('¡Nos alegra mucho darte la bienvenida a nuestra plataforma de aprendizaje!')
            ->line('Para comenzar tu viaje en el mundo de la Inteligencia Artificial, necesitamos verificar tu dirección de email.')
            ->action('Verificar mi Email ✓', $url)
            ->line('Este enlace expirará en 60 minutos por seguridad.')
            ->line('Si no creaste una cuenta en EasyWay2IA, puedes ignorar este email.')
            ->salutation('¡Esperamos verte pronto! 🚀');
    }
} 
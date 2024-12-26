<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentExpiredNotification extends Notification
{
    use Queueable;

    protected $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $estado = match($this->payment->status) {
            'failed' => 'fallido',
            'pending' => 'pendiente',
            'expired' => 'expirado',
            default => 'expirado',
        };

        return (new MailMessage)
            ->subject('Pago ' . $estado . ' en EasyWay2IA ⚠️')
            ->greeting('Hola ' . $notifiable->student->name)
            ->line('Te informamos que tu pago no se ha completado.')
            ->line('Detalles del pago:')
            ->line('• Monto: $' . number_format($this->payment->amount, 2))
            ->line('Si deseas completar tu compra, por favor realiza un nuevo intento de pago.')
            ->action('Realizar Nuevo Pago', $this->payment->buy_link)
            ->line('Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.')
            ->line('📧 Soporte: ' . config('mail.from.sales'))
            ->salutation('¡Gracias por tu interés en EasyWay2IA!');
    }
} 
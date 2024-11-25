<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PurchaseConfirmationNotification extends Notification
{
    use Queueable;

    protected $purchase;

    public function __construct($purchase)
    {
        $this->purchase = $purchase;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $mailMessage = (new MailMessage)
            ->subject('¡Gracias por tu compra!')
            ->greeting('¡Hola ' . $notifiable->student->name . '!')
            ->line('Tu compra se ha realizado con éxito.')
            ->line('Detalles de la compra:');

        if ($this->purchase->course) {
            $mailMessage->line('Curso: ' . $this->purchase->course->name)
                       ->line('Precio: $' . $this->purchase->amount);
        } elseif ($this->purchase->bundle) {
            $mailMessage->line('Bundle: ' . $this->purchase->bundle->name)
                       ->line('Precio: $' . $this->purchase->amount)
                       ->line('Cursos incluidos:');
            
            foreach ($this->purchase->bundle->courses as $course) {
                $mailMessage->line('- ' . $course->name);
            }
        }

        return $mailMessage
            ->line('Ya puedes acceder a tu contenido desde la plataforma.')
            ->action('Ir a la plataforma', config('app.frontend_url') . 'dashboard')
            ->line('¡Gracias por confiar en nosotros!');
    }
}
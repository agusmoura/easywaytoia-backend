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
            ->subject('¡Compra Exitosa en EasyWay2IA! 🎉')
            ->greeting('¡Felicitaciones ' . $notifiable->student->name . '! 🌟')
            ->line('Tu compra se ha completado exitosamente. ¡Bienvenido a tu nuevo viaje de aprendizaje!');

        if ($this->purchase->course_id) {
            $course = $this->purchase->course;
            $mailMessage
                ->line('📚 **Curso adquirido:**')
                ->line($course->name)
                ->line('📝 **Descripción:**')
                ->line($course->description)
                ->line('💰 **Inversión realizada:** $' . number_format($this->purchase->amount, 2));
        } elseif ($this->purchase->bundle_id) {
            $bundle = $this->purchase->bundle;
            $mailMessage
                ->line('🎯 **Bundle adquirido:**')
                ->line($bundle->name)
                ->line('📝 **Descripción:**')
                ->line($bundle->description)
                ->line('💰 **Inversión realizada:** $' . number_format($this->purchase->amount, 2))
                ->line('📚 **Cursos incluidos en tu bundle:**');
            
            foreach ($bundle->courses as $course) {
                $mailMessage->line('• ' . $course->name);
            }
        }

        return $mailMessage
            ->line('🚀 **Próximos pasos:**')
            ->line('1. Accede a tu panel de estudiante')
            ->line('2. Explora el contenido de tu curso')
            ->line('3. ¡Comienza a aprender!')
            ->action('Ir a Mi Panel de Aprendizaje', config('app.frontend_url') . '/pages/my-account')
            ->line('🤝 Si necesitas ayuda o tienes alguna pregunta, nuestro equipo de soporte está aquí para ayudarte.')
            ->line('📧 Puedes contactarnos en: ' . config('mail.from.address'))
            ->salutation('¡Éxitos en tu aprendizaje! 🚀');
    }
}
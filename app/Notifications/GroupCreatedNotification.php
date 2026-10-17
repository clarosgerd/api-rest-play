<?php

namespace App\Notifications;

use App\Models\Groups;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Groups $group) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tu grupo \"{$this->group->name}\" ya está listo")
            ->greeting("¡Hola {$notifiable->first_name}!")
            ->line("Tu grupo de pronósticos \"{$this->group->name}\" fue creado correctamente.")
            ->line("Código de invitación: {$this->group->invitation_code}")
            ->line('Compartilo con quienes quieras invitar a pronosticar en este grupo.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'group_id' => $this->group->group_id,
            'group_name' => $this->group->name,
            'invitation_code' => $this->group->invitation_code,
        ];
    }
}

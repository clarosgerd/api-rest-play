<?php

namespace App\Notifications;

use App\Models\Groups;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Groups $group,
        public string $invitationCode,
        public User $inviter,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $inviterName = $this->inviter->first_name.' '.$this->inviter->last_name;

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("You've been invited to \"{$this->group->name}\"")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("{$inviterName} has invited you to join the prediction group \"{$this->group->name}\".")
            ->line("Group description: {$this->group->description}")
            ->line("Invitation code: {$this->invitationCode}")
            ->action('Accept Invitation', url("/invitation/accept?code={$this->invitationCode}"))
            ->line('This invitation expires in 7 days.')
            ->line('If you don\'t have an account yet, register first and then use the invitation code.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'group_id' => $this->group->group_id,
            'group_name' => $this->group->name,
            'invitation_code' => $this->invitationCode,
            'inviter_name' => $this->inviter->first_name.' '.$this->inviter->last_name,
        ];
    }
}

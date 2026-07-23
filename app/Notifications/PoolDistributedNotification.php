<?php

namespace App\Notifications;

use App\Models\Groups;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PoolDistributedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Groups $group,
        public string $place,
        public float $prize,
        public int $totalPool,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $mail = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("Pool Prize Won! — {$this->group->name}")
            ->greeting("Congratulations, {$notifiable->first_name}!")
            ->line("You won **{$this->place}** place in the pool for group **{$this->group->name}**!")
            ->line("Prize: **{$this->prize}**")
            ->line("Total pool: {$this->totalPool}")
            ->line('The prize has been added to your balance.')
            ->action('View Leaderboard', url('/leaderboard'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'group_id' => $this->group->group_id,
            'group_name' => $this->group->name,
            'place' => $this->place,
            'prize' => $this->prize,
            'total_pool' => $this->totalPool,
        ];
    }
}

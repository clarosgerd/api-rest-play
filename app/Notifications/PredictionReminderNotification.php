<?php

namespace App\Notifications;

use App\Models\Groups;
use App\Models\Matches;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PredictionReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Matches $match,
        public Groups $group,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $matchTitle = $this->match->home->name.' vs '.$this->match->away->name;
        $matchDate = $this->match->match_date?->format('M d, Y H:i') ?? 'TBD';

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("Reminder: Make your prediction for {$matchTitle}")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("Don't forget to make your prediction for the match:")
            ->line("{$matchTitle}")
            ->line("Date: {$matchDate}")
            ->line("Group: {$this->group->name}")
            ->action('Make Prediction', url("/matches/{$this->match->match_id}/predict"))
            ->line('The cutoff time is 1 hour before the match starts.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'match_id' => $this->match->match_id,
            'match_title' => $this->match->home->name.' vs '.$this->match->away->name,
            'match_date' => $this->match->match_date,
            'group_id' => $this->group->group_id,
            'group_name' => $this->group->name,
        ];
    }
}

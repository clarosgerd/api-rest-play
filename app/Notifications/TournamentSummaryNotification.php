<?php

namespace App\Notifications;

use App\Models\Groups;
use App\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TournamentSummaryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public Groups $group,
        public int $totalPoints,
        public int $totalPredictions,
        public int $correctPredictions,
        public int $position,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $accuracy = $this->totalPredictions > 0
            ? round(($this->correctPredictions / $this->totalPredictions) * 100)
            : 0;

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("Tournament Summary: {$this->tournament->name} — {$this->totalPoints} points!")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("The {$this->tournament->name} {$this->tournament->season} has ended!")
            ->line("Here's your summary for group \"{$this->group->name}\":")
            ->line("Total points: {$this->totalPoints}")
            ->line("Predictions made: {$this->totalPredictions}")
            ->line("Correct predictions: {$this->correctPredictions}")
            ->line("Accuracy: {$accuracy}%")
            ->line("Your position: #{$this->position}")
            ->action('View Full Leaderboard', url("/groups/{$this->group->group_id}/leaderboard"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tournament_id' => $this->tournament->tournament_id,
            'tournament_name' => $this->tournament->name,
            'season' => $this->tournament->season,
            'group_id' => $this->group->group_id,
            'group_name' => $this->group->name,
            'total_points' => $this->totalPoints,
            'total_predictions' => $this->totalPredictions,
            'correct_predictions' => $this->correctPredictions,
            'position' => $this->position,
        ];
    }
}

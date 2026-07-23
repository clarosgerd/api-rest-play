<?php

namespace App\Notifications;

use App\Models\Matches;
use App\Models\Predictions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MatchPointsNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Matches $match,
        public Predictions $prediction,
        public int $pointsEarned,
        public string $predictionSummary,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $matchTitle = $this->match->home->name.' vs '.$this->match->away->name;
        $score = "{$this->match->home_score} - {$this->match->away_score}";

        $mail = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("Match Result: {$matchTitle} — You earned {$this->pointsEarned} points!")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line('The match has ended:')
            ->line("{$matchTitle}")
            ->line("Final score: {$score}")
            ->line("Your prediction: {$this->predictionSummary}")
            ->line("Points earned: {$this->pointsEarned}");

        if ($this->pointsEarned >= 10) {
            $mail->line('Incredible! You predicted the exact score!');
        } elseif ($this->pointsEarned >= 5) {
            $mail->line('Great job! You got the match result right!');
        } elseif ($this->pointsEarned > 0) {
            $mail->line('Nice! You got some points for this match.');
        } else {
            $mail->line('Better luck next time!');
        }

        $mail->action('View Leaderboard', url('/leaderboard'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'match_id' => $this->match->match_id,
            'match_title' => $this->match->home->name.' vs '.$this->match->away->name,
            'score' => "{$this->match->home_score}-{$this->match->away_score}",
            'points_earned' => $this->pointsEarned,
            'prediction_summary' => $this->predictionSummary,
        ];
    }
}

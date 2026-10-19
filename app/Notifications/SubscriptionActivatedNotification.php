<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Subscription $subscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = ucfirst($this->subscription->plan);

        return (new MailMessage)
            ->subject("Your {$plan} subscription is active")
            ->greeting("Hi {$notifiable->first_name},")
            ->line("Your payment was confirmed and your {$plan} subscription is now active.")
            ->line("It will renew on {$this->subscription->current_period_end->format('F j, Y')}.")
            ->action('Go to Dashboard', env('APP_URL').'/dashboard')
            ->line('Thank you for subscribing!');
    }
}

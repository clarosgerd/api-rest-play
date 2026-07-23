<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification
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
            ->subject("Your {$plan} subscription has expired")
            ->greeting("Hi {$notifiable->first_name},")
            ->line("Your {$plan} subscription has expired. You've been moved to the Free tier.")
            ->line('Your data is safe — you can resubscribe anytime to restore your premium features.')
            ->action('View Plans', env('APP_URL').'/subscription/plans')
            ->line('If you believe this is an error, please contact support.');
    }
}

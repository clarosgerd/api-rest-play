<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification
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
            ->subject("Your {$plan} subscription is expiring soon")
            ->greeting("Hi {$notifiable->first_name},")
            ->line("Your {$plan} subscription will expire on {$this->subscription->current_period_end->format('F j, Y')}.")
            ->line('Renew now to keep your premium features and avoid losing access.')
            ->action('Renew Subscription', env('APP_URL').'/subscription/renew')
            ->line('Thank you for being a valued member!');
    }
}

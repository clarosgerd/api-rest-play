<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
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
            ->subject("Payment failed for your {$plan} subscription")
            ->greeting("Hi {$notifiable->first_name},")
            ->line("We couldn't process your payment for your {$plan} subscription.")
            ->line('Please update your payment method to avoid service interruption.')
            ->action('Update Payment Method', env('APP_URL').'/subscription/billing')
            ->line('If you need assistance, please contact support.');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemindExpiringSubscriptions extends Command
{
    protected $signature = 'subscription:remind-expiring';

    protected $description = 'Send reminders to users with subscriptions expiring in 3 days';

    public function handle(): int
    {
        $expiringSubscriptions = Subscription::where('status', 'active')
            ->whereBetween('current_period_end', [
                Carbon::now(),
                Carbon::now()->addDays(3),
            ])
            ->get();

        $count = 0;

        foreach ($expiringSubscriptions as $subscription) {
            $user = $subscription->user;

            try {
                $user->notify(new \App\Notifications\SubscriptionExpiringNotification($subscription));
                $count++;
            } catch (\Exception $e) {
                $this->warn("Failed to notify user {$user->user_id}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$count} expiring subscription reminders");

        return Command::SUCCESS;
    }
}

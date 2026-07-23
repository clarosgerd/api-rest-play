<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'subscription:check-expired';

    protected $description = 'Downgrade users whose subscriptions have expired';

    public function handle(): int
    {
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('current_period_end', '<', Carbon::now())
            ->get();

        $count = 0;

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);

            $user = $subscription->user;
            $user->update([
                'subscription_tier' => 'free',
                'subscription_expires_at' => null,
            ]);

            try {
                $user->notify(new \App\Notifications\SubscriptionExpiredNotification($subscription));
            } catch (\Exception $e) {
                $this->warn("Failed to notify user {$user->user_id}: {$e->getMessage()}");
            }

            $count++;
        }

        $this->info("Processed {$count} expired subscriptions");

        return Command::SUCCESS;
    }
}

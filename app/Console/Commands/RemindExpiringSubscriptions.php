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
        // Ventana acotada a UN solo día calendario, exactamente 3 días antes
        // de vencer (19/10/2026) — antes era "entre ahora y +3 días", que
        // con este comando agendado a diario reenviaría el mismo recordatorio
        // 3 veces seguidas a la misma suscripción (día 3, día 2, día 1) hasta
        // que venciera. Así el cron diario la toca una sola vez.
        $expiringSubscriptions = Subscription::where('status', 'active')
            ->whereBetween('current_period_end', [
                Carbon::now()->addDays(3)->startOfDay(),
                Carbon::now()->addDays(3)->endOfDay(),
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

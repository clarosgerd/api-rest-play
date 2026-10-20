<?php

namespace App\Console\Commands;

use App\Models\PlanPromotion;
use App\Models\Tournament;
use Illuminate\Console\Command;

class ExpirePromotionsCommand extends Command
{
    protected $signature = 'promotions:expire';

    protected $description = 'Expire promotional tournaments and plan promotions that have passed their end date';

    public function handle(): int
    {
        $expired = Tournament::where('is_promotional', true)
            ->whereNotNull('promotional_ends_at')
            ->where('promotional_ends_at', '<', now())
            ->update(['is_promotional' => false]);

        $this->info("Expired {$expired} promotional tournament(s).");

        // Planes (20/10/2026) — mismo criterio que arriba.
        $expiredPlans = PlanPromotion::where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['is_active' => false]);

        $this->info("Expired {$expiredPlans} plan promotion(s).");

        return Command::SUCCESS;
    }
}

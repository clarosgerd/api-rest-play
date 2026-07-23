<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;

class ExpirePromotionsCommand extends Command
{
    protected $signature = 'promotions:expire';

    protected $description = 'Expire promotional tournaments that have passed their end date';

    public function handle(): int
    {
        $expired = Tournament::where('is_promotional', true)
            ->whereNotNull('promotional_ends_at')
            ->where('promotional_ends_at', '<', now())
            ->update(['is_promotional' => false]);

        $this->info("Expired {$expired} promotional tournament(s).");

        return Command::SUCCESS;
    }
}

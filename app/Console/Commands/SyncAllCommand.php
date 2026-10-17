<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncAllCommand extends Command
{
    protected $signature = 'sync:all
        {--type=all : What to sync: all, teams, fixtures, players, standings}
        {--season= : Season year (default: current year)}
        {--force : Ignore last_synced_at and re-sync everything}';

    protected $description = 'Sync all football data (football-data.org current seasons + ESPN live scores)';

    public function handle(): int
    {
        $type = $this->option('type');
        $season = $this->option('season') ?? date('Y');
        $force = (bool) $this->option('force');

        $this->info("=== SYNC ALL — {$type} | Season {$season}".($force ? ' | FORCED' : '').' ===');
        $this->newLine();

        $startTime = microtime(true);

        // 1. football-data.org FIRST: 13 competitions with CURRENT seasons
        //    (World Cup 2026, Copa Libertadores 2026, top European leagues)
        $this->info('--- football-data.org (current seasons) ---');
        $fdType = match ($type) {
            'fixtures' => 'matches',
            'players' => 'teams', // no players endpoint in free tier; teams closest
            default => $type,
        };
        Artisan::call('football:sync', array_filter([
            '--type' => $fdType,
            '--force' => $force ?: null,
        ]));
        $this->line(Artisan::output());
        $this->newLine();

        // 2. ESPN SECOND: live scores + leagues football-data.org lacks
        //    (Liga MX, MLS, Liga Argentina) — free & unlimited
        $this->info('--- ESPN (live scores + extra leagues) ---');
        Artisan::call('espn:sync', array_filter([
            '--type' => $type,
            '--season' => $season,
            '--force' => $force ?: null,
        ]));
        $this->line(Artisan::output());
        $this->newLine();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("=== SYNC ALL COMPLETE in {$elapsed}s ===");

        return 0;
    }
}

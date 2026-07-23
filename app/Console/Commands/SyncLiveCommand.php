<?php

namespace App\Console\Commands;

use App\Services\EspnService;
use App\Services\FootballDataService;
use Illuminate\Console\Command;

class SyncLiveCommand extends Command
{
    protected $signature = 'sync:live {--league= : Specific league. Omit for all.} {--type=all : all, fixtures, standings}';

    protected $description = 'Combined ESPN + football-data.org sync for live/current data (runs via scheduler every 6 hours)';

    public function handle(
        EspnService $espn,
        FootballDataService $fd
    ): int {
        $league = $this->option('league');
        $type = $this->option('type');
        $season = (string) date('Y');

        $this->info("sync:live — {$type} | {$season}" . ($league ? " | {$league}" : ''));
        $this->newLine();

        $exitCode = $this->call('espn:sync', array_filter([
            '--type' => $type,
            '--season' => $season,
            $league ? '--league' : null => $league,
        ]));

        if ($exitCode === 0) {
            $this->info('Sync completed successfully.');
        }

        return $exitCode;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\KaggleEloService;
use Illuminate\Console\Command;

class KaggleComputeEloCommand extends Command
{
    protected $signature = 'kaggle:compute-elo
        {--incremental : Only process new matches since last computation}
        {--up-to= : Compute Elo up to this date (YYYY-MM-DD)}';

    protected $description = 'Compute Elo ratings from the Kaggle International Football Results dataset';

    public function handle(KaggleEloService $kaggleElo): int
    {
        $incremental = (bool) $this->option('incremental');
        $upTo = $this->option('up-to');

        if ($incremental) {
            $count = $kaggleElo->computeIncremental();
            $this->info("Incremental update: {$count} new matches processed.");
        } else {
            $count = $kaggleElo->computeAll($upTo);
            $this->info("Full recompute: {$count} historical matches processed.");
        }

        $topTeams = $this->getTopTeams(20);
        $this->table(
            ['Team', 'Elo Rating', 'Matches', 'Last Match'],
            $topTeams
        );

        return Command::SUCCESS;
    }

    private function getTopTeams(int $limit): array
    {
        return \Illuminate\Support\Facades\DB::table('kaggle_elo_ratings')
            ->orderByDesc('elo_rating')
            ->limit($limit)
            ->get(['team_name', 'elo_rating', 'matches_played', 'last_match_date'])
            ->map(fn ($r) => [
                $r->team_name,
                $r->elo_rating,
                $r->matches_played,
                $r->last_match_date ?? 'N/A',
            ])
            ->all();
    }
}

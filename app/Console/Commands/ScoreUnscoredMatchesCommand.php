<?php

namespace App\Console\Commands;

use App\Jobs\ScoreMatchPredictions;
use App\Models\GroupPredictions;
use App\Models\PredictionResults;
use Illuminate\Console\Command;

class ScoreUnscoredMatchesCommand extends Command
{
    protected $signature = 'predictions:score-unscored';

    protected $description = 'Find finished matches with predictions but no results and dispatch scoring jobs';

    public function handle(): int
    {
        $scoredPredictionIds = PredictionResults::pluck('prediction_id')->toArray();

        $unscoredMatchIds = GroupPredictions::whereHas('prediction', function ($q) {
            $q->wherehas('match', function ($mq) {
                $mq->where('status', 'finished')->whereNotNull('home_score');
            });
        })
            ->whereNotIn('prediction_id', $scoredPredictionIds)
            ->pluck('prediction_id')
            ->unique();

        if ($unscoredMatchIds->isEmpty()) {
            $this->info('All finished matches are scored.');

            return 0;
        }

        $matchIds = \App\Models\Predictions::whereIn('prediction_id', $unscoredMatchIds)
            ->pluck('match_id')
            ->unique();

        $count = 0;

        foreach ($matchIds as $matchId) {
            ScoreMatchPredictions::dispatch($matchId);
            $count++;
        }

        $this->info("Dispatched scoring jobs for {$count} matches.");

        return 0;
    }
}

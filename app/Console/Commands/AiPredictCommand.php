<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Services\EloService;
use App\Services\PredictionEngineService;
use Illuminate\Console\Command;

class AiPredictCommand extends Command
{
    protected $signature = 'ai:predict
        {--tournament= : Specific tournament ID to predict}
        {--match= : Specific match ID to predict}
        {--evaluate : Only evaluate finished matches without generating new predictions}';

    protected $description = 'Generate AI predictions for upcoming matches and evaluate accuracy';

    public function handle(PredictionEngineService $engine, EloService $elo): int
    {
        $matchId = $this->option('match');
        $tournamentId = $this->option('tournament');
        $evaluateOnly = (bool) $this->option('evaluate');

        if (! $evaluateOnly) {
            if ($matchId) {
                $result = $engine->predict((int) $matchId);
                if ($result) {
                    $this->info("Prediction generated for match {$matchId}: {$result['predicted_result']} ({$result['predicted_home_score']}-{$result['predicted_away_score']})");
                } else {
                    $this->warn("Match {$matchId} not found or has no teams");
                }
            } elseif ($tournamentId) {
                $results = $engine->predictTournament((int) $tournamentId);
                $this->info("Generated {$results->count()} predictions for tournament {$tournamentId}");
            } else {
                $matches = Matches::where('status', 'scheduled')
                    ->whereDoesntHave('aiPrediction')
                    ->with(['home', 'away', 'tournament'])
                    ->take(200)
                    ->get();

                $count = 0;
                foreach ($matches as $match) {
                    $result = $engine->predict($match->match_id);
                    if ($result) {
                        $count++;
                    }
                }
                $this->info("Generated {$count} predictions for upcoming matches");
            }
        }

        // Evaluate finished matches and update Elo ratings
        $evaluatedMatches = $engine->evaluateFinished();
        $evaluatedCount = $evaluatedMatches->count();
        $this->info("Evaluated {$evaluatedCount} finished matches");

        if ($evaluatedCount > 0) {
            foreach ($evaluatedMatches as $match) {
                $elo->updateAfterMatch($match);
            }
            $this->info("Elo ratings updated for {$evaluatedCount} matches");
        }

        $stats = $engine->accuracyStats();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total evaluated', $stats['total']],
                ['Correct', $stats['correct']],
                ['Accuracy', number_format($stats['accuracy'] * 100, 2) . '%'],
                ['Model version', $stats['version']],
            ]
        );

        return Command::SUCCESS;
    }

}

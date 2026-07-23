<?php

namespace App\Jobs;

use App\Models\GroupPredictions;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\PredictionResults;
use App\Models\Predictions;
use App\Models\TournamentStage;
use App\Notifications\MatchPointsNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ScoreMatchPredictions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $matchId,
        public ?int $groupId = null,
    ) {}

    public function handle(): void
    {
        $match = Matches::find($this->matchId);

        if (! $match || $match->status !== 'finished') {
            return;
        }

        $query = GroupPredictions::whereHas('prediction', function ($q) use ($match) {
            $q->where('match_id', $match->match_id);
        })->with(['prediction', 'group']);

        if ($this->groupId) {
            $query->where('group_id', $this->groupId);
        }

        $groupPredictions = $query->get();

        if ($groupPredictions->isEmpty()) {
            return;
        }

        foreach ($groupPredictions as $gp) {
            $prediction = $gp->prediction;
            $group = $gp->group;

            if (! $prediction || ! $group) {
                continue;
            }

            $existing = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
            if ($existing) {
                continue;
            }

            $exactScorePredictors = $this->getExactScorePredictors($groupPredictions, $match, $group->group_id);
            $stageMultiplier = $this->getStageMultiplier($match, $group);

            $breakdown = $this->calculatePointsBreakdown($prediction, $match, $exactScorePredictors, $stageMultiplier, $group);
            $totalPoints = $breakdown['total'];

            DB::transaction(function () use ($gp, $prediction, $totalPoints, $breakdown) {
                PredictionResults::create([
                    'prediction_id' => $prediction->prediction_id,
                    'points_earned' => $totalPoints,
                    'points_breakdown' => json_encode($breakdown),
                    'processed_at' => Carbon::now(),
                ]);

                $gp->update(['points_awarded' => $totalPoints]);
            });

            $user = $prediction->user;
            if ($user && $user->email) {
                $summary = "{$prediction->predicted_home_score}-{$prediction->predicted_away_score}";
                try {
                    $user->notify(new MatchPointsNotification(
                        $match,
                        $prediction,
                        $totalPoints,
                        $summary
                    ));
                } catch (\Exception $e) {
                    // Notification failed, continue scoring
                }
            }
        }
    }

    private function calculatePointsBreakdown(
        Predictions $prediction,
        Matches $match,
        array $exactScorePredictors,
        float $stageMultiplier,
        Groups $group
    ): array {
        $breakdown = [
            'exact_score' => 0,
            'match_result' => 0,
            'correct_goal_home' => 0,
            'correct_goal_away' => 0,
            'unique_exact' => 0,
            'stage_multiplier' => $stageMultiplier,
            'subtotal' => 0,
            'total' => 0,
        ];

        $isExactScore = ($prediction->predicted_home_score == $match->home_score)
            && ($prediction->predicted_away_score == $match->away_score);

        if ($isExactScore) {
            $breakdown['exact_score'] = $group->exact_score_points ?? 10;
        }

        $actualResult = $this->getMatchResult($match);
        if ($prediction->predicted_result === $actualResult) {
            $breakdown['match_result'] = $group->match_result_points ?? 5;
        }

        if ($prediction->predicted_home_score == $match->home_score) {
            $breakdown['correct_goal_home'] = $group->correct_goal_points ?? 3;
        }

        if ($prediction->predicted_away_score == $match->away_score) {
            $breakdown['correct_goal_away'] = $group->correct_goal_points ?? 3;
        }

        if ($isExactScore && count($exactScorePredictors) === 1
            && in_array($prediction->user_id, $exactScorePredictors)) {
            $breakdown['unique_exact'] = $group->unique_exact_points ?? 15;
        }

        $breakdown['subtotal'] = $breakdown['exact_score']
            + $breakdown['match_result']
            + $breakdown['correct_goal_home']
            + $breakdown['correct_goal_away']
            + $breakdown['unique_exact'];

        $breakdown['total'] = (int) round($breakdown['subtotal'] * $stageMultiplier);

        return $breakdown;
    }

    private function getExactScorePredictors($groupPredictions, Matches $match, int $groupId): array
    {
        $userIds = [];

        foreach ($groupPredictions as $gp) {
            if ($gp->group_id !== $groupId) {
                continue;
            }

            $prediction = $gp->prediction;
            if (! $prediction) {
                continue;
            }

            if ($prediction->predicted_home_score == $match->home_score
                && $prediction->predicted_away_score == $match->away_score) {
                $userIds[] = $prediction->user_id;
            }
        }

        return $userIds;
    }

    private function getStageMultiplier(Matches $match, Groups $group): float
    {
        if (! $match->stage_id) {
            return 1.0;
        }

        $stage = TournamentStage::find($match->stage_id);

        if (! $stage || ! $stage->is_knockout) {
            return 1.0;
        }

        return $group->getStageMultiplier($stage->name);
    }

    private function getMatchResult(Matches $match): string
    {
        if ($match->home_score > $match->away_score) {
            return 'home_win';
        } elseif ($match->away_score > $match->home_score) {
            return 'away_win';
        }

        return 'draw';
    }
}

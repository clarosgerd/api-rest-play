<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MomentumService
{
    /**
     * Get momentum/psychology features for a team in a tournament.
     */
    public function getFeatures(int $teamId, ?int $tournamentId, int $limit = 15): array
    {
        $matches = $this->recentMatches($teamId, $tournamentId, $limit);
        $count = count($matches);

        if ($count < 3) {
            return $this->defaults();
        }

        $results = array_column($matches, 'result');
        $gf = array_column($matches, 'goals_for');
        $ga = array_column($matches, 'goals_against');

        // Current winning streak (consecutive wins from most recent)
        $streak = 0;
        foreach ($results as $r) {
            if ($r === 'win') {
                $streak++;
            } else {
                break;
            }
        }

        // Unbeaten streak (consecutive non-losses from most recent)
        $unbeaten = 0;
        foreach ($results as $r) {
            if ($r === 'win' || $r === 'draw') {
                $unbeaten++;
            } else {
                break;
            }
        }

        // Clean sheet %
        $cleanSheets = 0;
        $comebackMatches = 0;
        $comebacks = 0;
        $blowoutWins = 0;
        $blowoutLosses = 0;
        $shutoutLosses = 0;
        $draws = 0;

        foreach ($matches as $i => $m) {
            if ($m['goals_against'] === 0) {
                $cleanSheets++;
            }
            if ($m['conceded_first'] && $m['result'] === 'win') {
                $comebacks++;
            }
            if ($m['conceded_first']) {
                $comebackMatches++;
            }
            if ($m['result'] === 'win' && ($m['goals_for'] - $m['goals_against'] >= 3)) {
                $blowoutWins++;
            }
            if ($m['result'] === 'loss' && ($m['goals_against'] - $m['goals_for'] >= 3)) {
                $blowoutLosses++;
            }
            if ($m['result'] === 'loss' && $m['goals_for'] === 0) {
                $shutoutLosses++;
            }
            if ($m['result'] === 'draw') {
                $draws++;
            }
        }

        // Form: points in last 5 weighted by recency
        $formPts = 0;
        $formWeights = [0.35, 0.25, 0.2, 0.13, 0.07];
        $formTotalWeight = 0;
        foreach ($results as $j => $r) {
            if ($j >= 5) {
                break;
            }
            $w = $formWeights[$j] ?? 0.05;
            $formTotalWeight += $w;
            if ($r === 'win') {
                $formPts += 3 * $w;
            } elseif ($r === 'draw') {
                $formPts += 1 * $w;
            }
        }
        $weightedFormPts = $formTotalWeight > 0 ? $formPts / $formTotalWeight : 1.5;

        // Goals scored/conceded averages
        $recent5 = array_slice($matches, 0, 5);
        $avgFor = count($recent5) > 0 ? array_sum(array_column($recent5, 'goals_for')) / count($recent5) : 1.5;
        $avgAgainst = count($recent5) > 0 ? array_sum(array_column($recent5, 'goals_against')) / count($recent5) : 1.5;

        return [
            'streak' => $streak,
            'unbeaten_streak' => $unbeaten,
            'clean_sheet_pct' => round($cleanSheets / $count, 4),
            'comeback_rate' => $comebackMatches > 0 ? round($comebacks / $comebackMatches, 4) : 0,
            'draw_tendency' => round($draws / $count, 4),
            'blowout_win_pct' => round($blowoutWins / $count, 4),
            'blowout_loss_pct' => round($blowoutLosses / $count, 4),
            'shutout_loss_pct' => round($shutoutLosses / $count, 4),
            'weighted_form_pts' => round($weightedFormPts, 2),
            'goals_scored_avg_5' => round($avgFor, 2),
            'goals_conceded_avg_5' => round($avgAgainst, 2),
            'goal_diff_avg_5' => round($avgFor - $avgAgainst, 2),
            'matches_analyzed' => $count,
        ];
    }

    /**
     * Get recent matches for a team, ordered by date DESC.
     * Returns: result (win/draw/loss), goals_for, goals_against, conceded_first
     */
    private function recentMatches(int $teamId, ?int $tournamentId, int $limit): array
    {
        $query = DB::table('matches')
            ->where('status', 'finished')
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                  ->orWhere('away_team_id', $teamId);
            })
            ->orderBy('match_date', 'desc')
            ->limit($limit);

        if ($tournamentId) {
            $query->where('tournament_id', $tournamentId);
        }

        $rows = $query->get(['home_team_id', 'away_team_id', 'home_score', 'away_score', 'match_date']);

        $results = [];
        foreach ($rows as $row) {
            $isHome = (int) $row->home_team_id === $teamId;
            $goalsFor = $isHome ? (int) $row->home_score : (int) $row->away_score;
            $goalsAgainst = $isHome ? (int) $row->away_score : (int) $row->home_score;

            if ($goalsFor > $goalsAgainst) {
                $result = 'win';
            } elseif ($goalsFor === $goalsAgainst) {
                $result = 'draw';
            } else {
                $result = 'loss';
            }

            // Determine conceded_first by comparing scores
            // Simplified: if they scored fewer goals, they likely conceded first
            $concededFirst = $goalsAgainst > $goalsFor;

            $results[] = [
                'result' => $result,
                'goals_for' => $goalsFor,
                'goals_against' => $goalsAgainst,
                'conceded_first' => $concededFirst,
                'match_date' => $row->match_date,
            ];
        }

        return $results;
    }

    private function defaults(): array
    {
        return [
            'streak' => 0,
            'unbeaten_streak' => 0,
            'clean_sheet_pct' => 0.3,
            'comeback_rate' => 0.2,
            'draw_tendency' => 0.25,
            'blowout_win_pct' => 0.1,
            'blowout_loss_pct' => 0.1,
            'shutout_loss_pct' => 0.1,
            'weighted_form_pts' => 1.5,
            'goals_scored_avg_5' => 1.5,
            'goals_conceded_avg_5' => 1.5,
            'goal_diff_avg_5' => 0.0,
            'matches_analyzed' => 0,
        ];
    }
}

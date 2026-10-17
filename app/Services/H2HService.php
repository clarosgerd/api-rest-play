<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class H2HService
{
    /**
     * Get head-to-head features between two teams in a tournament.
     */
    public function getFeatures(int $homeTeamId, int $awayTeamId, ?int $tournamentId): array
    {
        $query = DB::table('matches')
            ->where('status', 'finished')
            ->where(function ($q) use ($homeTeamId, $awayTeamId) {
                $q->where(function ($sub) use ($homeTeamId, $awayTeamId) {
                    $sub->where('home_team_id', $homeTeamId)
                        ->where('away_team_id', $awayTeamId);
                })->orWhere(function ($sub) use ($homeTeamId, $awayTeamId) {
                    $sub->where('home_team_id', $awayTeamId)
                        ->where('away_team_id', $homeTeamId);
                });
            });

        if ($tournamentId) {
            $query->where('tournament_id', $tournamentId);
        }

        $matches = $query->get(['home_team_id', 'away_team_id', 'home_score', 'away_score', 'match_date']);
        $total = count($matches);

        if ($total === 0) {
            return [
                'h2h_matches' => 0,
                'h2h_home_win_rate' => 0.5,
                'h2h_home_win_pct' => 0.5,
                'h2h_draw_pct' => 0.25,
                'h2h_away_win_pct' => 0.25,
                'h2h_home_goal_diff' => 0.0,
                'h2h_total_goal_diff' => 0.0,
            ];
        }

        $homeWins = 0;
        $awayWins = 0;
        $draws = 0;
        $homeGoalsFor = 0;
        $homeGoalsAgainst = 0;
        $awayGoalsFor = 0;
        $awayGoalsAgainst = 0;

        foreach ($matches as $m) {
            $isHomeHome = (int) $m->home_team_id === $homeTeamId;
            $hs = (int) $m->home_score;
            $as = (int) $m->away_score;

            if ($isHomeHome) {
                $homeGoalsFor += $hs;
                $homeGoalsAgainst += $as;
            } else {
                $awayGoalsFor += $as;
                $awayGoalsAgainst += $hs;
            }

            if ($hs > $as) {
                if ($isHomeHome) {
                    $homeWins++;
                } else {
                    $awayWins++;
                }
            } elseif ($hs === $as) {
                $draws++;
            } else {
                if ($isHomeHome) {
                    $awayWins++;
                } else {
                    $homeWins++;
                }
            }
        }

        $homeGoalDiff = $homeGoalsFor - $homeGoalsAgainst;
        $awayGoalDiff = $awayGoalsFor - $awayGoalsAgainst;

        return [
            'h2h_matches' => $total,
            'h2h_home_win_rate' => $total > 0 ? round($homeWins / $total, 4) : 0.5,
            'h2h_home_win_pct' => $total > 0 ? round($homeWins / $total, 4) : 0.5,
            'h2h_draw_pct' => $total > 0 ? round($draws / $total, 4) : 0.25,
            'h2h_away_win_pct' => $total > 0 ? round($awayWins / $total, 4) : 0.25,
            'h2h_home_goal_diff' => round($homeGoalDiff / $total, 2),
            'h2h_total_goal_diff' => round(($homeGoalsFor + $awayGoalsFor - $homeGoalsAgainst - $awayGoalsAgainst) / $total, 2),
        ];
    }
}

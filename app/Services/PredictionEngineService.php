<?php

namespace App\Services;

use App\Models\AiPrediction;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PredictionEngineService
{
    public const VERSION = '2.2';

    // Ensemble weights (no Kaggle)
    public const WEIGHT_POISSON = 0.35;
    public const WEIGHT_ELO = 0.25;
    public const WEIGHT_FORM = 0.20;
    public const WEIGHT_H2H = 0.10;
    public const WEIGHT_MOMENTUM = 0.10;

    // Ensemble weights when Kaggle data is available
    public const KG_WEIGHT_POISSON = 0.30;
    public const KG_WEIGHT_KAGGLE_ELO = 0.20;
    public const KG_WEIGHT_ELO = 0.10;
    public const KG_WEIGHT_FORM = 0.18;
    public const KG_WEIGHT_H2H = 0.10;
    public const KG_WEIGHT_MOMENTUM = 0.12;

    public function __construct(
        protected EloService $elo,
        protected MomentumService $momentum,
        protected H2HService $h2h,
        protected KaggleEloService $kaggleElo,
        protected ConmebolService $conmebol,
    ) {}

    /**
     * Generate an AI prediction for a given match.
     */
    public function predict(int $matchId): ?array
    {
        $match = Matches::with(['home', 'away', 'tournament'])->find($matchId);

        if (! $match || ! $match->home || ! $match->away) {
            return null;
        }

        return $this->compute($match);
    }

    /**
     * Generate predictions for all upcoming matches in a tournament.
     */
    public function predictTournament(int $tournamentId): Collection
    {
        $matches = Matches::where('tournament_id', $tournamentId)
            ->where('status', 'scheduled')
            ->with(['home', 'away', 'tournament'])
            ->get();

        return $matches->map(fn ($m) => $this->compute($m))->filter();
    }

    /**
     * Evaluate finished matches that have AI predictions but no was_correct set.
     */
    public function evaluateFinished(int $limit = 100): Collection
    {
        $predicted = AiPrediction::whereNull('was_correct')
            ->whereHas('match', fn ($q) => $q->where('status', 'finished'))
            ->with('match')
            ->take($limit)
            ->get();

        foreach ($predicted as $p) {
            $actualScore = $p->match;
            $actualResult = $this->resultFromScore($actualScore->home_score, $actualScore->away_score);
            $p->was_correct = $p->predicted_result === $actualResult;
            $p->save();
        }

        return $predicted->pluck('match');
    }

    /**
     * Get accuracy stats for the AI model.
     */
    public function accuracyStats(): array
    {
        $total = AiPrediction::whereNotNull('was_correct')->count();
        $correct = AiPrediction::where('was_correct', true)->count();

        $byResult = AiPrediction::whereNotNull('was_correct')
            ->select('predicted_result', DB::raw('count(*) as total'), DB::raw('sum(case when was_correct then 1 else 0 end) as correct'))
            ->groupBy('predicted_result')
            ->get()
            ->keyBy('predicted_result');

        $byConfidence = AiPrediction::whereNotNull('was_correct')
            ->select(
                DB::raw('case when confidence >= 0.7 then \'high\' when confidence >= 0.4 then \'medium\' else \'low\' end as tier'),
                DB::raw('count(*) as total'),
                DB::raw('sum(case when was_correct then 1 else 0 end) as correct')
            )
            ->groupBy('tier')
            ->orderBy('tier')
            ->get();

        return [
            'total' => $total,
            'correct' => $correct,
            'accuracy' => $total > 0 ? round($correct / $total, 4) : 0,
            'version' => self::VERSION,
            'by_result' => $byResult,
            'by_confidence' => $byConfidence,
        ];
    }

    /**
     * Main computation: ensemble of Poisson + Elo + Form + H2H + Momentum.
     */
    private function compute(Matches $match): array
    {
        $homeTeam = $match->home;
        $awayTeam = $match->away;
        $tournament = $match->tournament;

        // — 1. League averages —
        $leagueAvg = $this->leagueAverages($tournament);

        // — 2. Team stats —
        $homeStats = $this->teamStats($homeTeam, $tournament, $leagueAvg)
            ?? $this->teamStatsGlobal($homeTeam, $leagueAvg);
        $awayStats = $this->teamStats($awayTeam, $tournament, $leagueAvg)
            ?? $this->teamStatsGlobal($awayTeam, $leagueAvg);

        // — 3. Elo features —
        $tournamentId = $tournament?->tournament_id;
        $eloFeatures = $this->elo->getMatchFeatures($homeTeam->team_id, $awayTeam->team_id, $tournamentId, false);

        // — 4. Momentum features —
        $homeMomentum = $this->momentum->getFeatures($homeTeam->team_id, $tournamentId);
        $awayMomentum = $this->momentum->getFeatures($awayTeam->team_id, $tournamentId);

        // — 5. H2H features —
        $h2hFeatures = $this->h2h->getFeatures($homeTeam->team_id, $awayTeam->team_id, $tournamentId);

        // — 6. Model A: Poisson expected goals —
        if ($homeStats && $awayStats) {
            $xgHome = $homeStats['attack_home'] * $awayStats['defense_away'] * $leagueAvg['goals_home'];
            $xgAway = $awayStats['attack_away'] * $homeStats['defense_home'] * $leagueAvg['goals_away'];
            $confidence = min($homeStats['confidence'], $awayStats['confidence']);
        } else {
            $xgHome = $leagueAvg['goals_home'];
            $xgAway = $leagueAvg['goals_away'];
            $confidence = 0.05;
        }

        // Elo-adjusted xG: if Elo difference is large, adjust xG
        $eloDiff = $eloFeatures['elo_diff'];
        if (abs($eloDiff) > 100) {
            $adjustment = 1.0 + ($eloDiff / 1500);
            $xgHome *= $adjustment;
            $xgAway /= $adjustment;
        }

        $xgHome = max(0.1, min(6, $xgHome));
        $xgAway = max(0.1, min(6, $xgAway));

        // — CONMEBOL altitude adjustment —
        $altitudeFactor = 1.0;
        $tournName = $tournament?->name;
        $isConmebol = $this->conmebol->isConmebolTournament($tournName)
            || $this->conmebol->isConmebolClash($homeTeam->country ?? '', $awayTeam->country ?? '');
        $homeStrikerFactor = 1.0;
        $awayStrikerFactor = 1.0;
        if ($isConmebol) {
            $altitudeFactor = $this->conmebol->altitudeFactor($homeTeam->country);
            $xgHome *= $altitudeFactor;

            // Franco conoce a los goleadores CONMEBOL: equipos con goleador
            // prolífico en Libertadores/Sudamericana reciben un boost de ataque
            $homeStrikerFactor = $this->strikerFactor($homeTeam);
            $awayStrikerFactor = $this->strikerFactor($awayTeam);
            $xgHome *= $homeStrikerFactor;
            $xgAway *= $awayStrikerFactor;
        }

        $homeProbs = $this->poisson($xgHome, 5);
        $awayProbs = $this->poisson($xgAway, 5);

        [$poissonHome, $poissonDraw, $poissonAway] = $this->jointProbs($homeProbs, $awayProbs);

        // — 7. Model B: Elo-based probabilities —
        $homeEloExpected = $eloFeatures['home_expected'];
        $tournEloExpected = $eloFeatures['home_tournament_expected'];

        // Blend global + tournament-specific Elo
        $eloHomeProb = $homeEloExpected * 0.5 + $tournEloExpected * 0.5;
        $eloAwayProb = 1.0 - $eloHomeProb;
        $eloDrawProb = min($eloHomeProb, $eloAwayProb) * 0.5;
        $eloHomeProb -= $eloDrawProb / 2;
        $eloAwayProb -= $eloDrawProb / 2;
        $eloTotal = $eloHomeProb + $eloDrawProb + $eloAwayProb;
        $eloHomeProb /= $eloTotal;
        $eloDrawProb /= $eloTotal;
        $eloAwayProb /= $eloTotal;

        // — 8. Model C: Form blend (existing) —
        $formBlend = $this->formBlend($homeTeam, $awayTeam, $tournament);
        if ($formBlend) {
            $formHome = $formBlend['home_win'];
            $formDraw = $formBlend['draw'];
            $formAway = $formBlend['away_win'];
        } else {
            $formHome = 0.4;
            $formDraw = 0.25;
            $formAway = 0.35;
        }

        // — 9. Model D: H2H-based probabilities —
        $h2hTotal = $h2hFeatures['h2h_matches'];
        if ($h2hTotal >= 2) {
            $h2hHome = $h2hFeatures['h2h_home_win_pct'];
            $h2hDraw = $h2hFeatures['h2h_draw_pct'];
            $h2hAway = $h2hFeatures['h2h_away_win_pct'];
        } else {
            $h2hHome = 0.4;
            $h2hDraw = 0.25;
            $h2hAway = 0.35;
        }

        // — 10. Model E: Kaggle Elo (for international matches) —
        $useKaggle = false;
        $kaggleHomeProb = 0.4;
        $kaggleDrawProb = 0.25;
        $kaggleAwayProb = 0.35;

        $hasBothKaggle = $this->kaggleElo->hasBothTeams($homeTeam->name, $awayTeam->name);

        // Franco conoce Sudamérica: fuerza Kaggle en torneos CONMEBOL aunque los
        // equipos no estén en Kaggle (usa el nombre del país como team_name)
        if (!$hasBothKaggle && $isConmebol) {
            $hasBothKaggle = $this->kaggleElo->hasBothTeams($homeTeam->country ?? '', $awayTeam->country ?? '');
        }

        if ($hasBothKaggle) {
            $useKaggle = true;
            $homeName = $this->kaggleElo->hasBothTeams($homeTeam->name, $awayTeam->name) ? $homeTeam->name : ($homeTeam->country ?? '');
            $awayName = $this->kaggleElo->hasBothTeams($homeTeam->name, $awayTeam->name) ? $awayTeam->name : ($awayTeam->country ?? '');
            $kgFeatures = $this->kaggleElo->getMatchFeatures($homeName, $awayName);
            $kgEloHomeExpected = $kgFeatures['home_expected'];
            $kaggleHomeProb = $kgEloHomeExpected;
            $kaggleAwayProb = 1.0 - $kgEloHomeExpected;
            $kaggleDrawProb = min($kaggleHomeProb, $kaggleAwayProb) * 0.4;
            $kaggleHomeProb -= $kaggleDrawProb / 2;
            $kaggleAwayProb -= $kaggleDrawProb / 2;
            $kgT = $kaggleHomeProb + $kaggleDrawProb + $kaggleAwayProb;
            $kaggleHomeProb /= $kgT;
            $kaggleDrawProb /= $kgT;
            $kaggleAwayProb /= $kgT;
        }

        // — 11. Momentum-based probabilities —
        $homeFormPts = $homeMomentum['weighted_form_pts'];
        $awayFormPts = $awayMomentum['weighted_form_pts'];
        $momTotal = $homeFormPts + $awayFormPts;
        if ($momTotal > 0) {
            $momHome = $homeFormPts / $momTotal;
            $momAway = $awayFormPts / $momTotal;
            $momDraw = 1.0 - max($momHome, $momAway);
            $momHome = ($momHome + 0.33) / 1.33;
            $momAway = ($momAway + 0.33) / 1.33;
            $momDraw = ($momDraw + 0.33) / 1.33;
            $momT = $momHome + $momDraw + $momAway;
            $momHome /= $momT;
            $momDraw /= $momT;
            $momAway /= $momT;
        } else {
            $momHome = 0.4;
            $momDraw = 0.25;
            $momAway = 0.35;
        }

        // — 12. Weighted ensemble (conditional on Kaggle availability) —
        if ($useKaggle) {
            $homeWinProb = $poissonHome * self::KG_WEIGHT_POISSON
                         + $eloHomeProb * self::KG_WEIGHT_ELO
                         + $kaggleHomeProb * self::KG_WEIGHT_KAGGLE_ELO
                         + $formHome * self::KG_WEIGHT_FORM
                         + $h2hHome * self::KG_WEIGHT_H2H
                         + $momHome * self::KG_WEIGHT_MOMENTUM;

            $drawProb = $poissonDraw * self::KG_WEIGHT_POISSON
                      + $eloDrawProb * self::KG_WEIGHT_ELO
                      + $kaggleDrawProb * self::KG_WEIGHT_KAGGLE_ELO
                      + $formDraw * self::KG_WEIGHT_FORM
                      + $h2hDraw * self::KG_WEIGHT_H2H
                      + $momDraw * self::KG_WEIGHT_MOMENTUM;

            $awayWinProb = $poissonAway * self::KG_WEIGHT_POISSON
                         + $eloAwayProb * self::KG_WEIGHT_ELO
                         + $kaggleAwayProb * self::KG_WEIGHT_KAGGLE_ELO
                         + $formAway * self::KG_WEIGHT_FORM
                         + $h2hAway * self::KG_WEIGHT_H2H
                         + $momAway * self::KG_WEIGHT_MOMENTUM;
        } else {
            $homeWinProb = $poissonHome * self::WEIGHT_POISSON
                         + $eloHomeProb * self::WEIGHT_ELO
                         + $formHome * self::WEIGHT_FORM
                         + $h2hHome * self::WEIGHT_H2H
                         + $momHome * self::WEIGHT_MOMENTUM;

            $drawProb = $poissonDraw * self::WEIGHT_POISSON
                      + $eloDrawProb * self::WEIGHT_ELO
                      + $formDraw * self::WEIGHT_FORM
                      + $h2hDraw * self::WEIGHT_H2H
                      + $momDraw * self::WEIGHT_MOMENTUM;

            $awayWinProb = $poissonAway * self::WEIGHT_POISSON
                         + $eloAwayProb * self::WEIGHT_ELO
                         + $formAway * self::WEIGHT_FORM
                         + $h2hAway * self::WEIGHT_H2H
                         + $momAway * self::WEIGHT_MOMENTUM;
        }

        // Normalize
        $total = $homeWinProb + $drawProb + $awayWinProb;
        $homeWinProb /= $total;
        $drawProb /= $total;
        $awayWinProb /= $total;

        // — 13. Most likely score from Poisson (already weighted highest) —
        $maxProb = 0;
        $mostLikelyScore = [0, 0];
        for ($i = 0; $i <= 5; $i++) {
            for ($j = 0; $j <= 5; $j++) {
                $p = $homeProbs[$i] * $awayProbs[$j];
                if ($p > $maxProb) {
                    $maxProb = $p;
                    $mostLikelyScore = [$i, $j];
                }
            }
        }

        // — 14. Determine result —
        if ($homeWinProb >= $drawProb && $homeWinProb >= $awayWinProb) {
            $predictedResult = 'home_win';
        } elseif ($drawProb >= $homeWinProb && $drawProb >= $awayWinProb) {
            $predictedResult = 'draw';
        } else {
            $predictedResult = 'away_win';
        }

        // Confidence based on how clear the prediction is
        $margin = max($homeWinProb, $drawProb, $awayWinProb)
                - (max($homeWinProb, $drawProb, $awayWinProb) === $homeWinProb
                    ? max($drawProb, $awayWinProb)
                    : (max($homeWinProb, $drawProb, $awayWinProb) === $drawProb
                        ? max($homeWinProb, $awayWinProb)
                        : max($homeWinProb, $drawProb)));
        $ensembleConfidence = min(1.0, max(0.05, 0.33 + $margin));

        // Blend with data-confidence
        $finalConfidence = round(($confidence * 0.3 + $ensembleConfidence * 0.7), 3);

        // Franco bonus: +10% confidence on CONMEBOL matches (conoce la región)
        if ($isConmebol) {
            $finalConfidence = round(min(1.0, $finalConfidence * 1.10), 3);

            // Extra bonus si un goleador claramente domina al rival
            if (abs($homeStrikerFactor - $awayStrikerFactor) > 0.08) {
                $finalConfidence = round(min(1.0, $finalConfidence * 1.05), 3);
            }
        }

        // — 14. Upsert prediction —
        AiPrediction::updateOrCreate(
            ['match_id' => $match->match_id],
            [
                'predicted_result' => $predictedResult,
                'predicted_home_score' => $mostLikelyScore[0],
                'predicted_away_score' => $mostLikelyScore[1],
                'home_win_prob' => round($homeWinProb, 4),
                'draw_prob' => round($drawProb, 4),
                'away_win_prob' => round($awayWinProb, 4),
                'confidence' => $finalConfidence,
                'model_version' => self::VERSION,
            ]
        );

        return [
            'match_id' => $match->match_id,
            'home_team' => $homeTeam->name,
            'away_team' => $awayTeam->name,
            'predicted_result' => $predictedResult,
            'predicted_home_score' => $mostLikelyScore[0],
            'predicted_away_score' => $mostLikelyScore[1],
            'home_win_prob' => round($homeWinProb, 4),
            'draw_prob' => round($drawProb, 4),
            'away_win_prob' => round($awayWinProb, 4),
            'confidence' => $finalConfidence,
            'model_version' => self::VERSION,
            'features' => [
                'elo_diff' => $eloDiff,
                'h2h_matches' => $h2hTotal,
                'home_streak' => $homeMomentum['streak'],
                'away_streak' => $awayMomentum['streak'],
                'home_form_pts' => $homeFormPts,
                'away_form_pts' => $awayFormPts,
                'kaggle_used' => $useKaggle,
                'is_conmebol' => $isConmebol,
                'altitude_factor' => round($altitudeFactor, 4),
                'home_striker_factor' => round($homeStrikerFactor, 4),
                'away_striker_factor' => round($awayStrikerFactor, 4),
            ],
        ];
    }

    private static ?float $strikerBaseline = null;

    /**
     * Ataque por goleadores: cuánto mejor que la media CONMEBOL es el mejor
     * delantero del equipo (max goles en una temporada de Libertadores/Sudamericana).
     * Factor en [0.9, 1.15].
     */
    private function strikerFactor(Team $team): float
    {
        if ($team->team_id === null) {
            return 1.0;
        }

        $best = (int) Player::where('team_id', $team->team_id)->max('goals');
        if ($best <= 0) {
            return 1.0;
        }

        $baseline = self::strikerBaseline();
        if ($baseline <= 0) {
            return 1.0;
        }

        $factor = 1.0 + (($best - $baseline) / $baseline) * 0.15;

        return round(max(0.9, min(1.15, $factor)), 4);
    }

    private static function strikerBaseline(): float
    {
        if (self::$strikerBaseline === null) {
            self::$strikerBaseline = (float) (Player::where('goals', '>', 0)->avg('goals') ?: 5.0);
        }

        return self::$strikerBaseline;
    }

    /**
     * Compute joint probabilities from two Poisson PMFs.
     */
    private function jointProbs(array $homeProbs, array $awayProbs): array
    {
        $homeWin = 0;
        $draw = 0;
        $awayWin = 0;

        for ($i = 0; $i <= 5; $i++) {
            for ($j = 0; $j <= 5; $j++) {
                $p = $homeProbs[$i] * $awayProbs[$j];
                if ($i > $j) {
                    $homeWin += $p;
                } elseif ($i === $j) {
                    $draw += $p;
                } else {
                    $awayWin += $p;
                }
            }
        }

        $total = $homeWin + $draw + $awayWin;

        return [$homeWin / $total, $draw / $total, $awayWin / $total];
    }

    /**
     * League averages: avg goals scored/conceded home/away for a tournament.
     * Falls back to global averages if no tournament matches exist.
     */
    private function leagueAverages(Tournament $tournament): array
    {
        $avg = $this->computeLeagueAverages($tournament->tournament_id);

        if ($avg && $avg['matches'] >= 10) {
            return $avg;
        }

        // Global fallback
        $global = DB::selectOne("
            SELECT
                AVG(home_score) as avg_home,
                AVG(away_score) as avg_away
            FROM matches
            WHERE status = 'finished'
        ");

        return [
            'goals_home' => (float) ($global->avg_home ?? 1.5),
            'goals_away' => (float) ($global->avg_away ?? 1.2),
        ];
    }

    private function computeLeagueAverages(int $tournamentId): ?array
    {
        $row = DB::selectOne("
            SELECT
                AVG(home_score) as avg_home,
                AVG(away_score) as avg_away,
                COUNT(*) as matches
            FROM matches
            WHERE tournament_id = ? AND status = 'finished'
        ", [$tournamentId]);

        if (! $row || ! $row->matches) {
            return null;
        }

        return [
            'goals_home' => (float) ($row->avg_home ?: 1.5),
            'goals_away' => (float) ($row->avg_away ?: 1.2),
            'matches' => (int) $row->matches,
        ];
    }

    /**
     * Team stats within a specific tournament.
     */
    private function teamStats(Team $team, Tournament $tournament, array $leagueAvg): ?array
    {
        $homeMatches = DB::selectOne("
            SELECT
                AVG(home_score) as avg_for,
                AVG(away_score) as avg_against,
                COUNT(*) as matches
            FROM matches
            WHERE home_team_id = ? AND tournament_id = ? AND status = 'finished'
        ", [$team->team_id, $tournament->tournament_id]);

        $awayMatches = DB::selectOne("
            SELECT
                AVG(away_score) as avg_for,
                AVG(home_score) as avg_against,
                COUNT(*) as matches
            FROM matches
            WHERE away_team_id = ? AND tournament_id = ? AND status = 'finished'
        ", [$team->team_id, $tournament->tournament_id]);

        $totalMatches = (int) ($homeMatches->matches ?? 0) + (int) ($awayMatches->matches ?? 0);

        if ($totalMatches < 2) {
            return null;
        }

        $avgHomeFor = (float) ($homeMatches->avg_for ?? $leagueAvg['goals_home']);
        $avgHomeAgainst = (float) ($homeMatches->avg_against ?? $leagueAvg['goals_away']);
        $avgAwayFor = (float) ($awayMatches->avg_for ?? $leagueAvg['goals_away']);
        $avgAwayAgainst = (float) ($awayMatches->avg_against ?? $leagueAvg['goals_home']);

        return [
            'attack_home' => $leagueAvg['goals_home'] > 0 ? $avgHomeFor / $leagueAvg['goals_home'] : 1,
            'defense_home' => $leagueAvg['goals_away'] > 0 ? $avgHomeAgainst / $leagueAvg['goals_away'] : 1,
            'attack_away' => $leagueAvg['goals_away'] > 0 ? $avgAwayFor / $leagueAvg['goals_away'] : 1,
            'defense_away' => $leagueAvg['goals_home'] > 0 ? $avgAwayAgainst / $leagueAvg['goals_home'] : 1,
            'confidence' => min(0.5, 0.1 + $totalMatches * 0.02),
        ];
    }

    /**
     * Global team stats across all tournaments (fallback).
     */
    private function teamStatsGlobal(Team $team, array $leagueAvg): ?array
    {
        $homeMatches = DB::selectOne("
            SELECT
                AVG(home_score) as avg_for,
                AVG(away_score) as avg_against,
                COUNT(*) as matches
            FROM matches
            WHERE home_team_id = ? AND status = 'finished'
        ", [$team->team_id]);

        $awayMatches = DB::selectOne("
            SELECT
                AVG(away_score) as avg_for,
                AVG(home_score) as avg_against,
                COUNT(*) as matches
            FROM matches
            WHERE away_team_id = ? AND status = 'finished'
        ", [$team->team_id]);

        $totalMatches = (int) ($homeMatches->matches ?? 0) + (int) ($awayMatches->matches ?? 0);

        if ($totalMatches < 2) {
            return null;
        }

        $avgHomeFor = (float) ($homeMatches->avg_for ?? $leagueAvg['goals_home']);
        $avgHomeAgainst = (float) ($homeMatches->avg_against ?? $leagueAvg['goals_away']);
        $avgAwayFor = (float) ($awayMatches->avg_for ?? $leagueAvg['goals_away']);
        $avgAwayAgainst = (float) ($awayMatches->avg_against ?? $leagueAvg['goals_home']);

        return [
            'attack_home' => $leagueAvg['goals_home'] > 0 ? $avgHomeFor / $leagueAvg['goals_home'] : 1,
            'defense_home' => $leagueAvg['goals_away'] > 0 ? $avgHomeAgainst / $leagueAvg['goals_away'] : 1,
            'attack_away' => $leagueAvg['goals_away'] > 0 ? $avgAwayFor / $leagueAvg['goals_away'] : 1,
            'defense_away' => $leagueAvg['goals_home'] > 0 ? $avgAwayAgainst / $leagueAvg['goals_home'] : 1,
            'confidence' => min(0.7, 0.15 + $totalMatches * 0.01),
        ];
    }

    /**
     * Recent form: last 5 matches for each team weighted by recency.
     * Returns weighted average of actual results.
     */
    private function formBlend(Team $home, Team $away, Tournament $tournament): ?array
    {
        $recentHome = $this->recentMatches($home->team_id, $tournament->tournament_id, 5);
        $recentAway = $this->recentMatches($away->team_id, $tournament->tournament_id, 5);

        if ($recentHome->isEmpty() && $recentAway->isEmpty()) {
            return null;
        }

        $weights = [0.35, 0.25, 0.2, 0.13, 0.07]; // Decay: most recent weighted most

        $applyWeights = function (Collection $matches) use ($weights) {
            $homeWin = 0;
            $draw = 0;
            $awayWin = 0;
            $totalWeight = 0;

            foreach ($matches->values() as $i => $m) {
                $w = $weights[$i] ?? 0.05;
                $totalWeight += $w;
                if ($m['goals_for'] > $m['goals_against']) {
                    $homeWin += $w;
                } elseif ($m['goals_for'] === $m['goals_against']) {
                    $draw += $w;
                } else {
                    $awayWin += $w;
                }
            }

            return [$homeWin / $totalWeight, $draw / $totalWeight, $awayWin / $totalWeight];
        };

        [$hW, $hD, $hL] = $applyWeights($recentHome);
        [$aW, $aD, $aL] = $applyWeights($recentAway);

        // Home form and away form combine
        $homeWinFactor = $hW * 0.7 + $aL * 0.3;
        $drawFactor = $hD * 0.5 + $aD * 0.5;
        $awayWinFactor = $hL * 0.3 + $aW * 0.7;

        $total = $homeWinFactor + $drawFactor + $awayWinFactor;

        return [
            'home_win' => $homeWinFactor / $total,
            'draw' => $drawFactor / $total,
            'away_win' => $awayWinFactor / $total,
        ];
    }

    /**
     * Last N matches for a team, ordered by date desc.
     */
    private function recentMatches(int $teamId, int $tournamentId, int $limit): Collection
    {
        $asHome = DB::select("
            SELECT home_score as goals_for, away_score as goals_against, match_date
            FROM matches
            WHERE home_team_id = ? AND tournament_id = ? AND status = 'finished'
            ORDER BY match_date DESC
            LIMIT ?
        ", [$teamId, $tournamentId, $limit]);

        $asAway = DB::select("
            SELECT away_score as goals_for, home_score as goals_against, match_date
            FROM matches
            WHERE away_team_id = ? AND tournament_id = ? AND status = 'finished'
            ORDER BY match_date DESC
            LIMIT ?
        ", [$teamId, $tournamentId, $limit]);

        $all = collect(array_merge($asHome, $asAway))
            ->sortByDesc('match_date')
            ->take($limit)
            ->map(fn ($m) => [
                'goals_for' => (int) $m->goals_for,
                'goals_against' => (int) $m->goals_against,
            ]);

        return $all;
    }

    /**
     * Poisson probability mass function: P(X=k) = (λ^k * e^(-λ)) / k!
     */
    private function poisson(float $lambda, int $max): array
    {
        $probs = [];
        for ($k = 0; $k <= $max; $k++) {
            $probs[$k] = exp(-$lambda) * pow($lambda, $k) / $this->factorial($k);
        }

        // Normalize
        $total = array_sum($probs);

        return array_map(fn ($p) => $p / $total, $probs);
    }

    private function factorial(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }

        $result = 1;
        for ($i = 2; $i <= $n; $i++) {
            $result *= $i;
        }

        return $result;
    }

    private function resultFromScore(int $home, int $away): string
    {
        if ($home > $away) {
            return 'home_win';
        }
        if ($home < $away) {
            return 'away_win';
        }

        return 'draw';
    }
}

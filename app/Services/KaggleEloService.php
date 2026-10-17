<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KaggleEloService
{
    public const INITIAL_RATING = 1500;
    public const HOME_ADVANTAGE = 100;
    public const K_FRIENDLY = 10;
    public const K_CUP = 25;
    public const K_LEAGUE = 15;
    public const K_OTHER = 20;

    private array $cache = [];

    /**
     * Replay all Kaggle matches chronologically to compute Elo ratings.
     */
    public function computeAll(?string $upToDate = null): int
    {
        $this->cache = [];
        DB::table('kaggle_elo_ratings')->truncate();

        $query = DB::table('kaggle_results')->orderBy('match_date')->orderBy('result_id');

        if ($upToDate) {
            $query->where('match_date', '<=', $upToDate);
        }

        $matches = $query->get(['result_id', 'match_date', 'home_team', 'away_team', 'home_score', 'away_score', 'tournament', 'neutral']);
        $count = 0;

        foreach ($matches as $match) {
            $this->processMatch($match);
            $count++;
        }

        // Persist all ratings
        $this->persistAll();

        return $count;
    }

    /**
     * Update Elo incrementally with new matches only (after the last computed date).
     */
    public function computeIncremental(): int
    {
        $lastDate = DB::table('kaggle_elo_ratings')->max('elo_as_of_date');

        // Load existing ratings into cache
        $existing = DB::table('kaggle_elo_ratings')->get(['team_name', 'elo_rating', 'matches_played', 'last_match_date']);
        foreach ($existing as $row) {
            $this->cache[$row->team_name] = [
                'rating' => (int) $row->elo_rating,
                'matches' => (int) $row->matches_played,
                'last_date' => $row->last_match_date,
            ];
        }

        $query = DB::table('kaggle_results')->orderBy('match_date')->orderBy('result_id');

        if ($lastDate) {
            $query->where('match_date', '>', $lastDate);
        }

        $matches = $query->get(['result_id', 'match_date', 'home_team', 'away_team', 'home_score', 'away_score', 'tournament', 'neutral']);

        if ($matches->isEmpty()) {
            return 0;
        }

        foreach ($matches as $match) {
            $this->processMatch($match);
        }

        $this->persistAll();
        $asOfDate = $matches->last()->match_date;
        DB::table('kaggle_elo_ratings')->update(['elo_as_of_date' => $asOfDate]);

        return $matches->count();
    }

    /**
     * Get Elo rating for a team.
     */
    public function getRating(string $teamName): int
    {
        if (isset($this->cache[$teamName])) {
            return $this->cache[$teamName]['rating'];
        }

        $row = DB::table('kaggle_elo_ratings')->where('team_name', $teamName)->first();
        $rating = $row ? (int) $row->elo_rating : self::INITIAL_RATING;
        $this->cache[$teamName] = ['rating' => $rating, 'matches' => $row->matches_played ?? 0, 'last_date' => $row->last_match_date ?? null];

        return $rating;
    }

    /**
     * Get match features for a given home/away team.
     */
    public function getMatchFeatures(string $homeTeam, string $awayTeam): array
    {
        $homeElo = $this->getRating($homeTeam);
        $awayElo = $this->getRating($awayTeam);

        return [
            'kaggle_home_elo' => $homeElo,
            'kaggle_away_elo' => $awayElo,
            'kaggle_elo_diff' => $homeElo - $awayElo,
            'home_expected' => $this->expectedScore($homeElo, $awayElo, self::HOME_ADVANTAGE),
        ];
    }

    /**
     * Check if Kaggle data is available for both teams.
     */
    public function hasBothTeams(string $homeTeam, string $awayTeam): bool
    {
        return $this->getRating($homeTeam) !== self::INITIAL_RATING
            && $this->getRating($awayTeam) !== self::INITIAL_RATING;
    }

    /**
     * Expected score for home team.
     */
    public function expectedScore(int $homeElo, int $awayElo, int $homeAdvantage = 0): float
    {
        return 1.0 / (1.0 + 10.0 ** (-($homeElo - $awayElo + $homeAdvantage) / 400.0));
    }

    private function processMatch(object $match): void
    {
        $home = $match->home_team;
        $away = $match->away_team;

        $homeElo = $this->getCachedRating($home);
        $awayElo = $this->getCachedRating($away);

        $isNeutral = (bool) $match->neutral;
        $ha = $isNeutral ? 0 : self::HOME_ADVANTAGE;
        $expected = $this->expectedScore($homeElo, $awayElo, $ha);

        $goalDiff = (int) $match->home_score - (int) $match->away_score;
        $gdMultiplier = $this->goalDiffMultiplier($goalDiff);

        $homeActual = $goalDiff > 0 ? 1.0 : ($goalDiff === 0 ? 0.5 : 0.0);
        $awayActual = 1.0 - $homeActual;

        $kFactor = $this->kFactor($match->tournament);

        $homeNew = round($homeElo + $kFactor * $gdMultiplier * ($homeActual - $expected));
        $awayNew = round($awayElo + $kFactor * $gdMultiplier * ($awayActual - (1 - $expected)));

        $this->cache[$home] = [
            'rating' => $homeNew,
            'matches' => ($this->cache[$home]['matches'] ?? 0) + 1,
            'last_date' => $match->match_date,
        ];

        $this->cache[$away] = [
            'rating' => $awayNew,
            'matches' => ($this->cache[$away]['matches'] ?? 0) + 1,
            'last_date' => $match->match_date,
        ];
    }

    private function getCachedRating(string $team): int
    {
        return $this->cache[$team]['rating'] ?? self::INITIAL_RATING;
    }

    private function goalDiffMultiplier(int $gd): float
    {
        $gd = abs($gd);
        if ($gd <= 1) return 1.0;
        if ($gd === 2) return 1.5;
        if ($gd === 3) return 1.75;
        return 1.75 + ($gd - 3) / 8;
    }

    private function kFactor(string $tournament): int
    {
        $t = strtolower($tournament);
        if (str_contains($t, 'friendly') || str_contains($t, 'amistoso')) {
            return self::K_FRIENDLY;
        }
        if (str_contains($t, 'cup') || str_contains($t, 'copa') || str_contains($t, 'world cup')
            || str_contains($t, 'championship') || str_contains($t, 'euro') || str_contains($t, 'afcon')
            || str_contains($t, 'nations league') || str_contains($t, 'play-off') || str_contains($t, 'playoff')) {
            return self::K_CUP;
        }
        if (str_contains($t, 'league') || str_contains($t, 'liga')) {
            return self::K_LEAGUE;
        }
        return self::K_OTHER;
    }

    private function persistAll(): void
    {
        $now = now();
        $lastDate = null;

        foreach ($this->cache as $team => $data) {
            DB::table('kaggle_elo_ratings')->updateOrInsert(
                ['team_name' => $team],
                [
                    'elo_rating' => $data['rating'],
                    'matches_played' => $data['matches'],
                    'last_match_date' => $data['last_date'],
                    'elo_as_of_date' => $data['last_date'],
                    'updated_at' => $now,
                ]
            );

            if ($data['last_date'] && (!$lastDate || $data['last_date'] > $lastDate)) {
                $lastDate = $data['last_date'];
            }
        }

        if ($lastDate) {
            DB::table('kaggle_elo_ratings')->update(['elo_as_of_date' => $lastDate]);
        }
    }
}

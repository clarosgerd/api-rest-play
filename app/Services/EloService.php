<?php

namespace App\Services;

use App\Models\Matches;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EloService
{
    public const INITIAL_RATING = 1500;
    public const HOME_ADVANTAGE = 100;

    // K-factors by tournament type
    public const K_LEAGUE = 15;
    public const K_CUP = 25;
    public const K_FRIENDLY = 10;

    /**
     * Get the current Elo rating for a team in a tournament.
     */
    public function getRating(int $teamId, ?int $tournamentId): int
    {
        $row = DB::table('team_elo_ratings')
            ->where('team_id', $teamId)
            ->where('tournament_id', $tournamentId)
            ->first();

        return $row ? (int) $row->elo_rating : self::INITIAL_RATING;
    }

    /**
     * Get both Elo ratings for a match and compute expected scores.
     */
    public function getMatchFeatures(int $homeTeamId, int $awayTeamId, ?int $tournamentId, bool $isNeutral): array
    {
        $homeElo = $this->getRating($homeTeamId, $tournamentId);
        $awayElo = $this->getRating($awayTeamId, $tournamentId);

        $ha = $isNeutral ? 0 : self::HOME_ADVANTAGE;
        $homeExpected = $this->expectedScore($homeElo, $awayElo, $ha);

        // Per-tournament Elo
        $homeTournElo = $this->getTournamentSpecific($homeTeamId, $tournamentId, $homeElo);
        $awayTournElo = $this->getTournamentSpecific($awayTeamId, $tournamentId, $awayElo);

        $tournHa = $isNeutral ? 0 : self::HOME_ADVANTAGE;
        $homeTournExpected = $this->expectedScore($homeTournElo, $awayTournElo, $tournHa);

        return [
            'home_elo' => $homeElo,
            'away_elo' => $awayElo,
            'elo_diff' => $homeElo - $awayElo,
            'elo_total' => $homeElo + $awayElo,
            'home_expected' => round($homeExpected, 4),
            'home_tournament_elo' => $homeTournElo,
            'away_tournament_elo' => $awayTournElo,
            'tournament_elo_diff' => $homeTournElo - $awayTournElo,
            'home_tournament_expected' => round($homeTournExpected, 4),
        ];
    }

    /**
     * Update Elo ratings after a finished match.
     */
    public function updateAfterMatch(Matches $match): void
    {
        if ($match->status !== 'finished') {
            return;
        }

        $isNeutral = false;
        $kFactor = $this->kFactor($match);
        $ha = $isNeutral ? 0 : self::HOME_ADVANTAGE;

        // Update global Elo
        $this->updateRating(
            $match->home_team_id, null,
            $match->away_team_id, null,
            $match->home_score, $match->away_score,
            $kFactor, $ha, $match->match_date
        );

        // Update per-tournament Elo
        if ($match->tournament_id) {
            $kFactor = $this->kFactor($match);
            $this->updateRating(
                $match->home_team_id, $match->tournament_id,
                $match->away_team_id, $match->tournament_id,
                $match->home_score, $match->away_score,
                $kFactor, $ha, $match->match_date
            );
        }
    }

    private function updateRating(
        int $homeTeamId, ?int $homeTournamentId,
        int $awayTeamId, ?int $awayTournamentId,
        int $homeScore, int $awayScore,
        int $kFactor, int $ha, string $matchDate
    ): void {
        $homeElo = $this->getRating($homeTeamId, $homeTournamentId);
        $awayElo = $this->getRating($awayTeamId, $awayTournamentId);

        $homeExpected = $this->expectedScore($homeElo, $awayElo, $ha);
        $goalDiff = $homeScore - $awayScore;
        $gdMultiplier = $this->goalDiffMultiplier($goalDiff);

        $homeActual = $goalDiff > 0 ? 1.0 : ($goalDiff === 0 ? 0.5 : 0.0);
        $awayActual = 1.0 - $homeActual;

        $homeNew = round($homeElo + $kFactor * $gdMultiplier * ($homeActual - $homeExpected));
        $awayNew = round($awayElo + $kFactor * $gdMultiplier * ($awayActual - (1 - $homeExpected)));

        $date = Carbon::parse($matchDate);

        foreach ([
            ['team_id' => $homeTeamId, 'tournament_id' => $homeTournamentId, 'rating' => $homeNew],
            ['team_id' => $awayTeamId, 'tournament_id' => $awayTournamentId, 'rating' => $awayNew],
        ] as $entry) {
            // Bug real encontrado al agregar tests (16/10/2026): DB::raw('matches_played + 1')
            // como VALUE de un updateOrInsert() solo tiene sentido en la rama UPDATE — en la
            // rama INSERT (equipo sin fila todavía) se convierte en un INSERT ... VALUES
            // (matches_played + 1), que no es SQL válido en ningún motor (sqlite ni MySQL):
            // "matches_played" no existe como columna referenciable en un INSERT. Se
            // incrementa en PHP en su lugar.
            $previousCount = DB::table('team_elo_ratings')
                ->where('team_id', $entry['team_id'])
                ->where('tournament_id', $entry['tournament_id'])
                ->value('matches_played') ?? 0;

            DB::table('team_elo_ratings')->updateOrInsert(
                ['team_id' => $entry['team_id'], 'tournament_id' => $entry['tournament_id']],
                [
                    'elo_rating' => $entry['rating'],
                    'matches_played' => $previousCount + 1,
                    'last_match_date' => $date,
                    'updated_at' => Carbon::now(),
                ]
            );
        }
    }

    /**
     * Expected score for home team (probability of winning).
     */
    public function expectedScore(int $homeElo, int $awayElo, int $homeAdvantage = 0): float
    {
        return 1.0 / (1.0 + 10.0 ** (-($homeElo - $awayElo + $homeAdvantage) / 400.0));
    }

    /**
     * Goal difference multiplier: bigger wins get more Elo change.
     */
    private function goalDiffMultiplier(int $gd): float
    {
        $gd = abs($gd);
        if ($gd <= 1) {
            return 1.0;
        }
        if ($gd === 2) {
            return 1.5;
        }
        if ($gd === 3) {
            return 1.75;
        }
        return 1.75 + ($gd - 3) / 8;
    }

    /**
     * Determine K-factor based on tournament type.
     */
    private function kFactor(Matches $match): int
    {
        if (!$match->tournament) {
            $match->load('tournament');
        }

        $name = $match->tournament?->name ?? '';

        if (stripos($name, 'friendly') !== false || stripos($name, 'amistoso') !== false) {
            return self::K_FRIENDLY;
        }
        if (stripos($name, 'cup') !== false || stripos($name, 'copa') !== false || stripos($name, 'playoff') !== false) {
            return self::K_CUP;
        }
        return self::K_LEAGUE;
    }

    /**
     * Get tournament-specific rating, falling back to global.
     */
    private function getTournamentSpecific(int $teamId, ?int $tournamentId, int $fallback): int
    {
        if (!$tournamentId) {
            return $fallback;
        }

        $row = DB::table('team_elo_ratings')
            ->where('team_id', $teamId)
            ->where('tournament_id', $tournamentId)
            ->first();

        return $row ? (int) $row->elo_rating : self::INITIAL_RATING;
    }
}

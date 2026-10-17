<?php

namespace App\Services;

class ConmebolService
{
    private const COUNTRY_ALTITUDE = [
        'Bolivia'   => 3640,
        'Ecuador'   => 2850,
        'Colombia'  => 2640,
        'Peru'      => 150,
        'Chile'     => 570,
        'Argentina' => 25,
        'Uruguay'   => 43,
        'Paraguay'  => 112,
        'Venezuela' => 900,
        'Brazil'    => 0,
    ];

    private const CONMEBOL_KEYWORDS = [
        'copa libertadores',
        'copa sudamericana',
        'copa américa',
        'copa america',
        'recopa',
        'conmebol',
        'world cup qualification',
        'world cup qualifier',
        'wcq',
        'fifa world cup qualifier',
        'sudamericana',
        'libertadores',
    ];

    private const ALTITUDE_THRESHOLD = 1000;

    /**
     * Altitude multiplier for home team (1.25 = +25% home advantage).
     */
    public function altitudeFactor(?string $country): float
    {
        $altitude = $this->getAltitude($country ?? '');
        if ($altitude <= self::ALTITUDE_THRESHOLD) {
            return 1.0;
        }
        $extra = $altitude - self::ALTITUDE_THRESHOLD;
        $factor = 1.0 + ($extra / 500) * 0.03;
        return min($factor, 1.25);
    }

    /**
     * Returns true if tournament is CONMEBOL-related (Franco's home turf).
     */
    public function isConmebolTournament(?string $tournamentName): bool
    {
        if (!$tournamentName) return false;
        $name = strtolower($tournamentName);
        foreach (self::CONMEBOL_KEYWORDS as $keyword) {
            if (str_contains($name, $keyword)) return true;
        }
        return false;
    }

    /**
     * Both teams are from CONMEBOL countries.
     */
    public function isConmebolClash(string $homeCountry, string $awayCountry): bool
    {
        return $this->isConmebolCountry($homeCountry)
            && $this->isConmebolCountry($awayCountry);
    }

    /**
     * At least one team is from a CONMEBOL country.
     */
    public function involvesConmebol(string $country): bool
    {
        return $this->isConmebolCountry($country);
    }

    public function isConmebolCountry(string $country): bool
    {
        return isset(self::COUNTRY_ALTITUDE[$country]);
    }

    public function getAltitude(string $country): int
    {
        return self::COUNTRY_ALTITUDE[$country] ?? 0;
    }
}

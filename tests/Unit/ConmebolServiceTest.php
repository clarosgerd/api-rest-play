<?php

namespace Tests\Unit;

use App\Services\ConmebolService;
use PHPUnit\Framework\TestCase;

class ConmebolServiceTest extends TestCase
{
    private ConmebolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConmebolService();
    }

    public function test_altitude_factor_is_1_below_threshold(): void
    {
        $this->assertSame(1.0, $this->service->altitudeFactor('Brazil'));
        $this->assertSame(1.0, $this->service->altitudeFactor('Argentina'));
        $this->assertSame(1.0, $this->service->altitudeFactor(null));
        $this->assertSame(1.0, $this->service->altitudeFactor('Narnia'));
    }

    public function test_altitude_factor_scales_above_threshold(): void
    {
        // Bolivia: 3640m, threshold 1000 -> extra 2640 -> +3% por cada 500 -> factor crece con la altura
        $bolivia = $this->service->altitudeFactor('Bolivia');
        $ecuador = $this->service->altitudeFactor('Ecuador');

        $this->assertGreaterThan(1.0, $ecuador);
        $this->assertGreaterThan($ecuador, $bolivia);
    }

    public function test_altitude_factor_exact_value_for_bolivia(): void
    {
        // (3640 - 1000) / 500 * 0.03 = 0.1584 -> 1.1584. Ningún país de
        // COUNTRY_ALTITUDE llega hoy al tope real de 1.25 (haría falta
        // ~5167m) — el cap existe como defensa, no se ejercita con los
        // datos actuales.
        $this->assertSame(1.1584, $this->service->altitudeFactor('Bolivia'));
    }

    public function test_is_conmebol_tournament_detects_keywords(): void
    {
        $this->assertTrue($this->service->isConmebolTournament('Copa Libertadores 2026'));
        $this->assertTrue($this->service->isConmebolTournament('CONMEBOL Copa Sudamericana'));
        $this->assertTrue($this->service->isConmebolTournament('World Cup Qualification - South America'));
        $this->assertFalse($this->service->isConmebolTournament('Premier League'));
        $this->assertFalse($this->service->isConmebolTournament(null));
    }

    public function test_is_conmebol_country(): void
    {
        $this->assertTrue($this->service->isConmebolCountry('Bolivia'));
        $this->assertTrue($this->service->isConmebolCountry('Brazil'));
        $this->assertFalse($this->service->isConmebolCountry('Spain'));
        $this->assertFalse($this->service->isConmebolCountry(''));
    }

    public function test_is_conmebol_clash_requires_both_teams(): void
    {
        $this->assertTrue($this->service->isConmebolClash('Argentina', 'Brazil'));
        $this->assertFalse($this->service->isConmebolClash('Argentina', 'Spain'));
        $this->assertFalse($this->service->isConmebolClash('Spain', 'France'));
    }

    public function test_get_altitude_returns_zero_for_unknown_country(): void
    {
        $this->assertSame(0, $this->service->getAltitude('Narnia'));
        $this->assertSame(3640, $this->service->getAltitude('Bolivia'));
    }
}

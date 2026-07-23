<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ApiFootballService
{
    protected $client;

    protected $baseUrl = 'https://v3.football.api-sports.io';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 15,
            'headers' => [
                'x-apisports-key' => config('services.api_football.key', ''),
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getLeagues(): array
    {
        return $this->request('GET', '/leagues');
    }

    public function getTeams(int $leagueId, int $season): array
    {
        return $this->request('GET', '/teams', [
            'league' => $leagueId,
            'season' => $season,
        ]);
    }

    public function getFixtures(int $leagueId, int $season): array
    {
        return $this->request('GET', '/fixtures', [
            'league' => $leagueId,
            'season' => $season,
        ]);
    }

    public function getFixtureById(int $fixtureId): array
    {
        return $this->request('GET', '/fixtures', [
            'id' => $fixtureId,
        ]);
    }

    public function getStandings(int $leagueId, int $season): array
    {
        return $this->request('GET', '/standings', [
            'league' => $leagueId,
            'season' => $season,
        ]);
    }

    public function getPlayers(int $leagueId, int $season, int $page = 1): array
    {
        return $this->request('GET', '/players', [
            'league' => $leagueId,
            'season' => $season,
            'page' => $page,
        ]);
    }

    public function getTopScorers(int $leagueId, int $season): array
    {
        return $this->request('GET', '/players/topscorers', [
            'league' => $leagueId,
            'season' => $season,
        ]);
    }

    public function request(string $method, string $uri, array $queryParams = []): array
    {
        try {
            $response = $this->client->request($method, $uri, [
                'query' => $queryParams,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            Log::error("API-Football error: {$statusCode} on {$uri}");

            if ($statusCode === 429) {
                sleep(60);

                return $this->request($method, $uri, $queryParams);
            }

            return [];
        } catch (\Exception $e) {
            Log::error("API-Football request failed: {$e->getMessage()}");

            return [];
        }
    }
}

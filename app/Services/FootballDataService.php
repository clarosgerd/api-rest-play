<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class FootballDataService extends BaseApiService
{
    protected $baseUrl = 'https://api.football-data.org/v4';

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,
            'headers' => [
                'X-Auth-Token' => config('services.football_data.token', ''),
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getCompetitions(): array
    {
        return $this->request('GET', '/competitions');
    }

    public function getCompetition(int $id): array
    {
        return $this->request('GET', "/competitions/{$id}");
    }

    public function getCompetitionTeams(int $competitionId): array
    {
        return $this->request('GET', "/competitions/{$competitionId}/teams");
    }

    public function getCompetitionMatches(int $competitionId, array $params = []): array
    {
        return $this->request('GET', "/competitions/{$competitionId}/matches", $params);
    }

    public function getStandings(int $competitionId): array
    {
        return $this->request('GET', "/competitions/{$competitionId}/standings");
    }

    public function getMatch(int $matchId): array
    {
        return $this->request('GET', "/matches/{$matchId}");
    }

    private function request(string $method, string $uri, array $queryParams = []): array
    {
        return $this->requestWithRetry($method, $uri, $queryParams);
    }
}

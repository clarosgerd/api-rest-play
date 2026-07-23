<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class EspnService
{
    protected Client $client;

    protected string $baseUrl = 'https://site.api.espn.com/apis/site/v2/sports/soccer';

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getScoreboard(string $leagueId, ?string $date = null): array
    {
        $params = [];
        if ($date) {
            $params['dates'] = $date;
        }

        return $this->request("{$leagueId}/scoreboard", $params);
    }

    public function getTeams(string $leagueId): array
    {
        return $this->request("{$leagueId}/teams");
    }

    public function getRoster(string $leagueId, int $teamId): array
    {
        return $this->request("{$leagueId}/teams/{$teamId}/roster");
    }

    public function getSummary(string $leagueId, int $eventId): array
    {
        return $this->request("{$leagueId}/summary", ['event' => $eventId]);
    }

    public function request(string $path, array $queryParams = []): array
    {
        try {
            $options = [];
            if (! empty($queryParams)) {
                $options['query'] = $queryParams;
            }

            $response = $this->client->request('GET', $this->baseUrl . '/' . $path, $options);
            $body = json_decode($response->getBody()->getContents(), true);

            return $body ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            Log::error("ESPN API error: {$statusCode} on {$path}");

            return [];
        } catch (\Exception $e) {
            Log::error("ESPN API request failed: {$e->getMessage()}");

            return [];
        }
    }
}

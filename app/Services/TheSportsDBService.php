<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TheSportsDBService
{
    protected $client;

    protected $baseUrl = 'https://www.thesportsdb.com/api/v1/json';

    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.thesportsdb.key', '123');
        $this->client = new Client([
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function lookupLeague(int $leagueId): array
    {
        return $this->request("lookupleague.php?id={$leagueId}");
    }

    public function lookupTeam(int $teamId): array
    {
        return $this->request("lookupteam.php?id={$teamId}");
    }

    public function lookupEvent(int $eventId): array
    {
        return $this->request("lookupevent.php?id={$eventId}");
    }

    public function getLeagueTeams(string $leagueName): array
    {
        $name = urlencode(str_replace(' ', '_', $leagueName));

        return $this->request("search_all_teams.php?l={$name}");
    }

    public function getLeagueNextEvents(int $leagueId): array
    {
        return $this->request("eventsnextleague.php?id={$leagueId}");
    }

    public function getLeaguePastEvents(int $leagueId): array
    {
        return $this->request("eventspastleague.php?id={$leagueId}");
    }

    public function getSeasonEvents(int $leagueId, string $season): array
    {
        return $this->request("eventsseason.php?id={$leagueId}&s={$season}");
    }

    public function getSeasons(int $leagueId): array
    {
        return $this->request("search_all_seasons.php?id={$leagueId}");
    }

    private function request(string $endpoint): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->apiKey}/{$endpoint}";
            $response = $this->client->get($url);
            $body = json_decode($response->getBody()->getContents(), true);

            return $body ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            Log::error("TheSportsDB API error: {$statusCode} on {$endpoint}");

            if ($statusCode === 429) {
                sleep(60);

                return $this->request($endpoint);
            }

            return [];
        } catch (\Exception $e) {
            Log::error("TheSportsDB request failed: {$e->getMessage()}");

            return [];
        }
    }
}

<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

abstract class BaseApiService
{
    protected $client;

    protected $baseUrl;

    /**
     * Make an HTTP request with exponential backoff retry on rate limit (429).
     *
     * @param  string  $method  HTTP method
     * @param  string  $uri  Endpoint URI
     * @param  array  $queryParams  Query parameters
     * @param  int  $attempt  Current attempt number (internal use)
     * @return array Decoded JSON response
     */
    protected function requestWithRetry(string $method, string $uri, array $queryParams = [], int $attempt = 1): array
    {
        $maxAttempts = 3;
        $baseDelay = 60; // seconds

        try {
            $response = $this->client->request($method, $this->baseUrl.$uri, [
                'query' => $queryParams,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            Log::error("API error: {$statusCode} on {$uri} (attempt {$attempt}/{$maxAttempts})");

            if ($statusCode === 429 && $attempt < $maxAttempts) {
                $delay = $baseDelay * pow(2, $attempt - 1); // 60, 120, 240 seconds
                Log::info("Rate limited. Retrying in {$delay} seconds...");
                sleep($delay);

                return $this->requestWithRetry($method, $uri, $queryParams, $attempt + 1);
            }

            return [];
        } catch (\Exception $e) {
            Log::error("API request failed: {$e->getMessage()}");

            return [];
        }
    }
}

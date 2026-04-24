<?php

namespace App\Services\Centrifugo;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CentrifugoClient
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $apiKey,
        private readonly string $tokenHmacSecret,
        private readonly int $connectionTokenTtl,
        private readonly int $subscriptionTokenTtl,
        private readonly int $httpTimeout,
    ) {
    }

    public function publish(string $channel, array $data): void
    {
        $this->call('publish', ['channel' => $channel, 'data' => $data]);
    }

    public function broadcast(array $channels, array $data): void
    {
        if (empty($channels)) {
            return;
        }

        $this->call('broadcast', ['channels' => array_values($channels), 'data' => $data]);
    }

    public function generateConnectionToken(string $userId): string
    {
        return JWT::encode([
            'sub' => $userId,
            'exp' => time() + $this->connectionTokenTtl,
        ], $this->tokenHmacSecret, 'HS256');
    }

    public function generateSubscriptionToken(string $userId, string $channel): string
    {
        return JWT::encode([
            'sub' => $userId,
            'channel' => $channel,
            'exp' => time() + $this->subscriptionTokenTtl,
        ], $this->tokenHmacSecret, 'HS256');
    }

    private function call(string $method, array $params): void
    {
        $response = Http::withHeaders($this->headers())
            ->timeout($this->httpTimeout)
            ->acceptJson()
            ->asJson()
            ->post(rtrim($this->url, '/') . '/api/' . $method, $params);

        if ($response->failed()) {
            Log::warning('Centrifugo HTTP error', [
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return;
        }

        $body = $response->json();
        if (is_array($body) && isset($body['error'])) {
            Log::warning('Centrifugo API error', [
                'method' => $method,
                'error' => $body['error'],
            ]);
        }
    }

    private function headers(): array
    {
        $headers = [];
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['X-API-Key'] = $this->apiKey;
        }
        return $headers;
    }
}

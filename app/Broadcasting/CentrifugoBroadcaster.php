<?php

namespace App\Broadcasting;

use App\Services\Centrifugo\CentrifugoClient;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CentrifugoBroadcaster extends Broadcaster
{
    public function __construct(
        private readonly CentrifugoClient $client,
        private readonly array $namespaceMap,
    ) {
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        $originClient = $payload['socket'] ?? null;
        unset($payload['socket']);

        $envelope = [
            'event' => $event,
            'data' => $payload,
        ];

        if ($originClient !== null) {
            $envelope['_origin_client'] = $originClient;
        }

        $mapped = [];
        foreach ($this->formatChannels($channels) as $laravelChannel) {
            $centrifugoChannel = $this->mapChannelName($laravelChannel);
            if ($centrifugoChannel !== null) {
                $mapped[] = $centrifugoChannel;
            }
        }

        if ($mapped === []) {
            return;
        }

        $this->client->broadcast($mapped, $envelope);
    }

    public function auth($request)
    {
        throw new AccessDeniedHttpException(
            'Pusher-style channel auth is not supported. Use POST /api/realtime/subscribe.'
        );
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $result;
    }

    public function userCanAccessChannel(Request $request, string $centrifugoChannel): bool
    {
        $laravelChannel = $this->reverseMapChannelName($centrifugoChannel);
        if ($laravelChannel === null) {
            return false;
        }

        try {
            $result = $this->verifyUserCanAccessChannel($request, $laravelChannel);
        } catch (AccessDeniedHttpException) {
            return false;
        }

        return $result !== false;
    }

    private function mapChannelName(string $laravelChannel): ?string
    {
        $name = str_starts_with($laravelChannel, 'private-')
            ? substr($laravelChannel, strlen('private-'))
            : $laravelChannel;

        foreach ($this->namespaceMap as $prefix => $namespace) {
            if (str_starts_with($name, $prefix . '.')) {
                $id = substr($name, strlen($prefix) + 1);
                return $namespace . ':' . $id;
            }
        }

        return null;
    }

    private function reverseMapChannelName(string $centrifugoChannel): ?string
    {
        foreach ($this->namespaceMap as $prefix => $namespace) {
            if (str_starts_with($centrifugoChannel, $namespace . ':')) {
                $id = substr($centrifugoChannel, strlen($namespace) + 1);
                return $prefix . '.' . $id;
            }
        }

        return null;
    }
}

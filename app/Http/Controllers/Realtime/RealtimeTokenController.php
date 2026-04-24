<?php

namespace App\Http\Controllers\Realtime;

use App\Broadcasting\CentrifugoBroadcaster;
use App\Http\Controllers\Controller;
use App\Services\Centrifugo\CentrifugoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class RealtimeTokenController extends Controller
{
    public function __construct(
        private readonly CentrifugoClient $client,
    ) {
    }

    public function connect(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        return response()->json([
            'token' => $this->client->generateConnectionToken($userId),
            'url' => config('centrifugo.public_url'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string', 'max:255'],
        ]);

        $channel = $data['channel'];

        $broadcaster = Broadcast::driver('centrifugo');
        if (! $broadcaster instanceof CentrifugoBroadcaster) {
            return response()->json(['message' => 'Realtime driver not available.'], 503);
        }

        if (! $broadcaster->userCanAccessChannel($request, $channel)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $userId = (string) $request->user()->id;

        return response()->json([
            'token' => $this->client->generateSubscriptionToken($userId, $channel),
            'channel' => $channel,
        ]);
    }
}

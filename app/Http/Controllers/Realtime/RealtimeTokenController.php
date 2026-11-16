<?php

namespace App\Http\Controllers\Realtime;

use App\Broadcasting\CentrifugoBroadcaster;
use App\Http\Controllers\Controller;
use App\Http\Requests\Realtime\SubscribeRequest;
use App\Services\Realtime\CentrifugoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class RealtimeTokenController extends Controller
{
    public function __construct(
        private readonly CentrifugoClient $client,
    ) {}

    public function connect(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        return response()->json([
            'token' => $this->client->generateConnectionToken($userId),
            'url' => config('centrifugo.public_url'),
        ]);
    }

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $channel = $request->validated('channel');

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

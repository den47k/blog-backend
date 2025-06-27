<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePrivateConversationRequest;
use App\Http\Resources\ConversationDetailsResource;
use App\Http\Resources\ConversationResource;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    public function __construct(private ConversationService $conversationService) {}


    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = $this->conversationService->getConversationsForUser($request->user());
        return ConversationResource::collection($conversations);
    }

    public function createPrivateConversation(CreatePrivateConversationRequest $request): JsonResponse
    {
        $recipient = User::findOrFail($request->user_id);

        if ($request->user()->id === $recipient->id) return response()->json(['message' => 'Can\'t create conversation with yourseld'], 422);

        $conversation = $this->conversationService->createPrivateConversation(
            $request->user(),
            $recipient,
            $request->should_join_now
        );

        return response()->json([
            'message' => 'Conversation created successfully',
            'conversation' => new ConversationDetailsResource($conversation)
        ], 201);
    }

    public function createGroupConversation(): JsonResponse
    {
        return response()->json([], 201);
    }
}

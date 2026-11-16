<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\CreatePrivateConversationRequest;
use App\Http\Resources\Conversation\ConversationResource;
use App\Models\Conversation;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $conversations = $this->conversationService->getConversationsForUser($user);

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, string $tag): ConversationResource
    {
        $user = $request->user();
        $conversation = $this->conversationService->getPrivateConversation($user, $tag);
        Gate::authorize('view', $conversation);

        return new ConversationResource($conversation);
    }

    public function createPrivateConversation(CreatePrivateConversationRequest $request): JsonResponse
    {
        $conversation = $this->conversationService->createPrivateConversation(
            $request->user(),
            $request->validated('user_id'),
            $request->boolean('should_join_now'),
        );

        return response()->json([
            'message' => 'Conversation created successfully',
            'conversation' => new ConversationResource($conversation),
        ], 201);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('delete', $conversation);
        $this->conversationService->deleteConversation($conversation, $request->user());

        return response()->json(['message' => 'Conversation deleted successfully']);
    }

    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('markAsRead', $conversation);
        $this->conversationService->markConversationAsRead($conversation, $request->user());

        return response()->json(['message' => 'Conversation marked as read.']);
    }
}

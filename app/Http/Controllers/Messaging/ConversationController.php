<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\CreatePrivateConversationRequest;
use App\Http\Resources\Conversation\ConversationResource;
use App\Models\Conversation;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        return ConversationResource::collectionFor($conversations, $user);
    }

    public function show(Request $request, string $tag): ConversationResource
    {
        $user = $request->user();
        $conversation = $this->conversationService->getPrivateConversation($user, $tag);
        Gate::authorize('view', $conversation);

        return new ConversationResource($conversation, $user);
    }

    public function createPrivateConversation(CreatePrivateConversationRequest $request): JsonResponse
    {
        $conversation = $this->conversationService->createPrivateConversation(
            $request->user(),
            $request->validated('user_id'),
            $request->boolean('should_join_now'),
        );

        return (new ConversationResource($conversation, $request->user()))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('delete', $conversation);
        $this->conversationService->deleteConversation($conversation, $request->user());

        return response()->noContent();
    }

    public function markAsRead(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('markAsRead', $conversation);
        $this->conversationService->markConversationAsRead($conversation, $request->user());

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePrivateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
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
        $conversations = $this->conversationService->getConversationsForUser($request->user());

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, string $tag): ConversationResource
    {
        $targetUser = User::where('tag', $tag)->firstOrFail();
        $conversation = Conversation::findExistingConversation($request->user(), $targetUser);

        if (!$conversation) {
            abort(404, 'Conversation not found');
        }

        Gate::authorize('view', $conversation);

        return new ConversationResource($conversation);
    }

    public function createPrivateConversation(CreatePrivateConversationRequest $request): JsonResponse
    {
        $recipient = User::findOrFail($request->user_id);

        if ($request->user()->id === $recipient->id) {
            return response()->json(['message' => 'Cannot create conversation with yourself'], 422);
        }

        $conversation = $this->conversationService->createPrivateConversation(
            $request->user(),
            $recipient,
            $request->should_join_now
        );

        return response()->json([
            'message' => 'Conversation created successfully',
            'conversation' => new ConversationResource($conversation)
        ], 201);
    }

    // public function createGroupConversation()
    // {
    //     //
    // }

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

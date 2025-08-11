<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteMessageRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function __construct(private MessageService $messageService) {}

    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        $messages = $this->messageService->getMessagesForConversation($conversation);

        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        Log::info($request->all());

        $message = $this->messageService->storeMessage(
            $conversation,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => [
                'message' => new MessageResource($message->load(['user', 'recipients'])),
                'conversation' => new ConversationResource($conversation)
            ] 
        ], 201);
    }

    public function update(UpdateMessageRequest $request, Conversation $conversation, Message $message): JsonResponse
    {
        Gate::authorize('update', $message);

        $message = $this->messageService->updateMessage(
            $message,
            $request->validated()
        );

        return response()->json([
            'message' => 'Message updated successfully',
            'data' => new MessageResource($message->load(['user', 'recipients'])),
        ]);
    }

    public function delete(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        Gate::authorize('delete', $message);

        $data = $this->messageService->deleteMessage($conversation, $message);
        
        return response()->json([
            'message' => 'Message deleted successfully',
            ...$data,
        ]);
    }
}

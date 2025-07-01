<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(private MessageService $messageService) {}

    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        // Gate::authorize('view', $conversation); ToDo

        $messages = $this->messageService->getMessagesForConversation($conversation);

        return MessageResource::collection($messages);
    }

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->messageService->storeMessage(
            $conversation,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => new MessageResource($message->load('user'))
        ], 201);
    }

    // public function markAsRead() {} ToDO
}

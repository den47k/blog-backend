<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('conversations')->group(function () {
    Route::get('/', [ConversationController::class, 'index'])->name('conversation.index');
    Route::get('/private/{tag}', [ConversationController::class, 'show'])->name('conversation.private.show');
    Route::post('/private', [ConversationController::class, 'createPrivateConversation'])->name('conversation.private');
    // Route::post('/group', [ConversationController::class, 'createGroupConversation'])->name('conversation.group');
    Route::delete('/{conversation:id}', [ConversationController::class, 'destroy'])->name('conversation.destroy');
    Route::post('/{conversation:id}/mark-as-read', [ConversationController::class, 'markAsRead'])->name('conversation.markAsRead');

    Route::get('/{conversation:id}/messages', [MessageController::class, 'index'])->name('conversation.messages.index');
    Route::post('/{conversation:id}/messages', [MessageController::class, 'store'])->name('conversation.messages.store');
    Route::patch('/{conversation:id}/messages/{message:id}', [MessageController::class, 'update'])->name('conversation.messages.update');
    Route::delete('/{conversation:id}/messages/{message:id}', [MessageController::class, 'delete'])->name('conversation.messages.delete');
});

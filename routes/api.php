<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\FriendController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password/{token}', [AuthController::class, 'resetPassword']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
});

Route::middleware('auth:api')->group(function () {
    // Users
    Route::get('/users/profile', [UserController::class, 'profile']);
    Route::post('/users/upload-avatar', [UserController::class, 'uploadAvatar']);
    Route::get('/search', [UserController::class, 'search']);

    // Conversations
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index']);
        Route::post('/', [ConversationController::class, 'accessChat']);
        Route::put('/{conversationId}/read', [ConversationController::class, 'markAsRead']);
        Route::post('/group', [ConversationController::class, 'createGroup']);
        Route::get('/{conversationId}/polls', [ConversationController::class, 'getPolls']);
        Route::post('/{conversationId}/polls', [ConversationController::class, 'createPoll']);
        Route::get('/{conversationId}/notes', [ConversationController::class, 'getNotes']);
        Route::post('/{conversationId}/notes', [ConversationController::class, 'createNote']);
        Route::put('/{id}', [ConversationController::class, 'updateConversation']);
        Route::get('/{conversationId}/participants', [ConversationController::class, 'getParticipants']);
        Route::post('/{conversationId}/participants', [ConversationController::class, 'addMember']);
        Route::delete('/{conversationId}/participants/{userId}', [ConversationController::class, 'removeMember']);
    });

    // Notes and Polls (from conversation routes in express)
    Route::put('/notes/{noteId}', [ConversationController::class, 'updateNote']);
    Route::delete('/notes/{noteId}', [ConversationController::class, 'deleteNote']);
    Route::post('/polls/{pollId}/vote', [ConversationController::class, 'votePoll']);

    // Messages
    Route::prefix('messages')->group(function () {
        Route::post('/', [MessageController::class, 'sendMessage']);
        Route::get('/search', [MessageController::class, 'searchMessages']);
        Route::get('/{conversationId}', [MessageController::class, 'getHistory']);
    });

    // Friends
    Route::prefix('friend')->group(function () {
        Route::get('/', [FriendController::class, 'index']);
        Route::post('/add', [FriendController::class, 'sendFriendRequest']);
        Route::post('/accept', [FriendController::class, 'acceptFriendRequest']);
        Route::post('/decline', [FriendController::class, 'declineFriendRequest']);
        Route::get('/requests', [FriendController::class, 'getFriendRequests']);
        Route::get('/status/{targetUserId}', [FriendController::class, 'getFriendStatus']);
        Route::post('/cancel', [FriendController::class, 'cancelFriendRequest']);
        Route::delete('/{friendId}', [FriendController::class, 'unfriend']);
    });
});

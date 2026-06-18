<?php

namespace App\Services;

use App\Repositories\MessageRepositoryInterface;
use App\Repositories\ConversationRepositoryInterface;
use App\Services\CloudinaryService;
use App\Events\MessageSent;

class MessageService
{
    protected $messageRepository;
    protected $conversationRepository;
    protected $cloudinaryService;

    public function __construct(
        MessageRepositoryInterface $messageRepository,
        ConversationRepositoryInterface $conversationRepository,
        CloudinaryService $cloudinaryService
    ) {
        $this->messageRepository = $messageRepository;
        $this->conversationRepository = $conversationRepository;
        $this->cloudinaryService = $cloudinaryService;
    }

    public function getHistory($conversationId, $page = 1, $limit = 50)
    {
        return $this->messageRepository->getHistory($conversationId, $page, $limit);
    }

    public function searchMessages($keyword, $conversationId, $userId)
    {
        if ($conversationId) {
            return $this->messageRepository->search($conversationId, $keyword, 'text');
        }

        // If no conversationId, search all conversations user is part of.
        // Wait, the repository currently searches by conversation ID.
        // To keep it simple, if no conversationId, we query the model directly for now.
        // In a real scenario, we should add a method to MessageRepositoryInterface.
        $query = \App\Models\Message::where('content', 'LIKE', "%{$keyword}%")
            ->where('message_type', 'text')
            ->whereHas('conversation.users', function($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->with(['sender', 'conversation'])
            ->orderByDesc('created_at');

        return $query->get()->all();
    }

    public function sendMessage($conversationId, $userId, $content, $messageType, $replyToMessageId, $file = null)
    {
        $fileUrl = null;
        $fileName = null;
        $fileSize = null;
        $mimeType = null;

        if ($file) {
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            if (strpos($mimeType, 'image/') === 0) {
                $messageType = 'image';
            } elseif (strpos($mimeType, 'video/') === 0) {
                $messageType = 'video';
            } else {
                $messageType = 'file';
            }

            // Upload using CloudinaryService
            $fileUrl = $this->cloudinaryService->uploadFile($file, 'chat_uploads');
            if (!$fileUrl) {
                throw new \Exception("Lỗi upload file");
            }
        }

        $message = $this->messageRepository->create([
            'sender_id' => $userId,
            'conversation_id' => $conversationId,
            'content' => $content,
            'message_type' => $messageType,
            'file_url' => $fileUrl,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'reply_to_message_id' => $replyToMessageId,
        ]);

        // Update latest message in conversation
        $this->conversationRepository->update($conversationId, ['latest_message_id' => $message->id]);

        $message->load(['sender', 'readBy', 'replyToMessage']);

        return $message;
    }
}

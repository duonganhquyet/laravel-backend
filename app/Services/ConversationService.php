<?php

namespace App\Services;

use App\Repositories\ConversationRepositoryInterface;
use App\Repositories\PollRepositoryInterface;
use App\Repositories\NoteRepositoryInterface;
use App\Repositories\MessageRepositoryInterface;

class ConversationService
{
    protected $conversationRepository;
    protected $pollRepository;
    protected $noteRepository;
    protected $messageRepository;

    public function __construct(
        ConversationRepositoryInterface $conversationRepository,
        PollRepositoryInterface $pollRepository,
        NoteRepositoryInterface $noteRepository,
        MessageRepositoryInterface $messageRepository
    ) {
        $this->conversationRepository = $conversationRepository;
        $this->pollRepository = $pollRepository;
        $this->noteRepository = $noteRepository;
        $this->messageRepository = $messageRepository;
    }

    public function getUserConversations($userId)
    {
        return $this->conversationRepository->getUserConversations($userId);
    }

    public function accessDirectChat($userId, $otherUserId)
    {
        $conversation = $this->conversationRepository->findDirectChat($userId, $otherUserId);

        if ($conversation) {
            return $conversation;
        }

        return $this->conversationRepository->createDirectChat($userId, $otherUserId);
    }

    public function createGroupChat($name, $userIds, $creatorId)
    {
        if (count($userIds) < 1) {
            throw new \Exception("Cần ít nhất 2 người để tạo nhóm");
        }

        $userIds[] = $creatorId;

        return $this->conversationRepository->createGroupChat($name, $userIds, $creatorId);
    }

    public function updateConversationName($id, $name)
    {
        $this->conversationRepository->update($id, ['chat_name' => $name]);
        return $this->conversationRepository->findById($id);
    }

    public function getParticipants($conversationId)
    {
        $conversation = $this->conversationRepository->findById($conversationId);
        return $conversation ? $conversation->users : [];
    }

    public function addMember($conversationId, $userId)
    {
        if (!$this->conversationRepository->isMember($conversationId, $userId)) {
            $this->conversationRepository->addMember($conversationId, $userId);
        }
        return true;
    }

    public function removeMember($conversationId, $userId)
    {
        return $this->conversationRepository->removeMember($conversationId, $userId);
    }

    public function markAsRead($conversationId, $userId)
    {
        $conversation = $this->conversationRepository->findById($conversationId);
        
        if (!$conversation) {
            return false;
        }

        $unreadMessages = $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereDoesntHave('readBy', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->get();

        foreach ($unreadMessages as $message) {
            $message->readBy()->attach($userId);
        }

        return true;
    }

    // --- Polls ---
    public function getPolls($conversationId)
    {
        return $this->pollRepository->getByConversation($conversationId);
    }

    public function createPoll($conversationId, $question, $options, $creatorId)
    {
        $data = [
            'conversation_id' => $conversationId,
            'question' => $question,
            'created_by' => $creatorId,
            'is_active' => true
        ];

        return $this->pollRepository->create($data, $options);
    }

    public function votePoll($pollId, $optionId, $userId)
    {
        // Check if poll exists
        $poll = $this->pollRepository->findById($pollId);
        if (!$poll) {
            throw new \Exception("Poll không tồn tại");
        }

        // Delete existing votes by user in this poll
        $optionIds = $poll->options->pluck('id')->toArray();
        \DB::table('poll_votes')
            ->whereIn('poll_option_id', $optionIds)
            ->where('user_id', $userId)
            ->delete();

        return $this->pollRepository->vote($optionId, $userId);
    }

    // --- Notes ---
    public function getNotes($conversationId)
    {
        return $this->noteRepository->getByConversation($conversationId);
    }

    public function createNote($conversationId, $content, $creatorId)
    {
        $data = [
            'conversation_id' => $conversationId,
            'content' => $content,
            'created_by' => $creatorId
        ];
        return $this->noteRepository->create($data);
    }

    public function updateNote($noteId, $content)
    {
        return $this->noteRepository->update($noteId, ['content' => $content]);
    }

    public function deleteNote($noteId)
    {
        return $this->noteRepository->delete($noteId);
    }
}

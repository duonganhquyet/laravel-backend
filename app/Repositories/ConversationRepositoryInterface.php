<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\User;

interface ConversationRepositoryInterface
{
    public function findById(int $id): ?Conversation;
    public function getUserConversations(int $userId): array;
    public function findDirectChat(int $userId, int $otherUserId): ?Conversation;
    public function createDirectChat(int $userId, int $otherUserId): Conversation;
    public function createGroupChat(string $chatName, array $userIds, int $creatorId): Conversation;
    public function update(int $id, array $data): bool;
    public function addMember(int $conversationId, int $userId, bool $isAdmin = false): bool;
    public function removeMember(int $conversationId, int $userId): bool;
    public function isMember(int $conversationId, int $userId): bool;
    public function isAdmin(int $conversationId, int $userId): bool;
}

<?php

namespace App\Repositories;

use App\Models\Poll;
use App\Models\PollVote;

interface PollRepositoryInterface
{
    public function findById(int $id): ?Poll;
    public function getByConversation(int $conversationId): array;
    public function create(array $data, array $options): Poll;
    public function vote(int $pollOptionId, int $userId): ?PollVote;
}

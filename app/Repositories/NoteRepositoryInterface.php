<?php

namespace App\Repositories;

use App\Models\Note;

interface NoteRepositoryInterface
{
    public function findById(int $id): ?Note;
    public function getByConversation(int $conversationId): array;
    public function create(array $data): Note;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}

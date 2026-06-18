<?php

namespace App\Repositories;

use App\Models\Note;

class NoteRepository implements NoteRepositoryInterface
{
    public function findById(int $id): ?Note
    {
        return Note::with('creator')->find($id);
    }

    public function getByConversation(int $conversationId): array
    {
        return Note::where('conversation_id', $conversationId)
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    public function create(array $data): Note
    {
        return Note::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $note = Note::find($id);
        if ($note) {
            return $note->update($data);
        }
        return false;
    }

    public function delete(int $id): bool
    {
        $note = Note::find($id);
        if ($note) {
            return $note->delete();
        }
        return false;
    }
}

<?php

namespace App\Repositories;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Illuminate\Support\Facades\DB;

class PollRepository implements PollRepositoryInterface
{
    public function findById(int $id): ?Poll
    {
        return Poll::with(['options.voters', 'creator'])->find($id);
    }

    public function getByConversation(int $conversationId): array
    {
        return Poll::where('conversation_id', $conversationId)
            ->with(['options.voters', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    public function create(array $data, array $optionTexts): Poll
    {
        return DB::transaction(function () use ($data, $optionTexts) {
            $poll = Poll::create($data);

            foreach ($optionTexts as $text) {
                PollOption::create([
                    'poll_id' => $poll->id,
                    'text' => $text,
                ]);
            }

            return $poll->load('options.voters', 'creator');
        });
    }

    public function vote(int $pollOptionId, int $userId): ?PollVote
    {
        return DB::transaction(function () use ($pollOptionId, $userId) {
            // Find option and poll
            $option = PollOption::find($pollOptionId);
            if (!$option) return null;

            $pollId = $option->poll_id;

            // Remove existing votes by this user on options belonging to this poll
            PollVote::whereHas('pollOption', function ($query) use ($pollId) {
                $query->where('poll_id', $pollId);
            })->where('user_id', $userId)->delete();

            // Add the new vote
            return PollVote::create([
                'poll_option_id' => $pollOptionId,
                'user_id' => $userId,
            ]);
        });
    }
}

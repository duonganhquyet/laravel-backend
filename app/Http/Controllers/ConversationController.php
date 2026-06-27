<?php

namespace App\Http\Controllers;

use App\Services\ConversationService;
use App\Services\MessageService;
use App\Events\MessageSent;
use App\Events\NewMessageNotification;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    protected $conversationService;
    protected $messageService;

    public function __construct(ConversationService $conversationService, MessageService $messageService)
    {
        $this->conversationService = $conversationService;
        $this->messageService = $messageService;
    }

    public function index(Request $request)
    {
        $userId = auth()->id();
        $conversations = $this->conversationService->getUserConversations($userId);

        $formatted = array_map(function($conv) {
            return $this->formatConversation($conv);
        }, $conversations);

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    public function accessChat(Request $request)
    {
        $request->validate(['userId' => 'required']);
        $userId = $request->userId;
        $currentUserId = auth()->id();

        $conversation = $this->conversationService->accessDirectChat($currentUserId, $userId);

        return response()->json([
            'success' => true,
            'data' => $this->formatConversation($conversation)
        ]);
    }

    public function createGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'users' => 'required|array|min:1' // User IDs
        ]);

        try {
            $groupChat = $this->conversationService->createGroupChat($request->name, $request->users, auth()->id());

            return response()->json([
                'success' => true,
                'data' => $this->formatConversation($groupChat)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function updateConversation(Request $request, $id)
    {
        $request->validate(['chatName' => 'required|string']);
        
        $conversation = $this->conversationService->updateConversationName($id, $request->chatName);

        return response()->json([
            'success' => true,
            'data' => $this->formatConversation($conversation)
        ]);
    }

    public function getParticipants($conversationId)
    {
        $users = $this->conversationService->getParticipants($conversationId);
        
        $formatted = $users->map(function($user) {
            return [
                '_id' => (string) $user->id,
                'id' => $user->id,
                'fullName' => $user->full_name,
                'avatar' => $user->avatar,
                'isAdmin' => $user->pivot->is_admin
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    public function addMember(Request $request, $conversationId)
    {
        $request->validate(['userId' => 'required']);
        
        $this->conversationService->addMember($conversationId, $request->userId);

        return response()->json(['success' => true, 'message' => 'Đã thêm thành viên']);
    }

    public function removeMember($conversationId, $userId)
    {
        $this->conversationService->removeMember($conversationId, $userId);

        return response()->json(['success' => true, 'message' => 'Đã xóa thành viên']);
    }

    public function markAsRead($conversationId)
    {
        $this->conversationService->markAsRead($conversationId, auth()->id());

        return response()->json(['success' => true]);
    }

    // --- Polls ---
    public function getPolls($conversationId)
    {
        $polls = $this->conversationService->getPolls($conversationId);
        
        $formatted = array_map(function($poll) {
            return [
                'PollId' => (string) $poll->id,
                'ConversationId' => (string) $poll->conversation_id,
                'Question' => $poll->question,
                'IsActive' => $poll->is_active,
                'CreatedByUserId' => (string) $poll->created_by,
                'CreatedAt' => $poll->created_at,
                'Options' => $poll->options->map(function($opt) {
                    return [
                        'OptionId' => (string) $opt->id,
                        'PollId' => (string) $opt->poll_id,
                        'OptionText' => $opt->text,
                        'VoterIds' => $opt->voters->pluck('id')->map(fn($id) => (string) $id)->toArray()
                    ];
                })->toArray()
            ];
        }, $polls);

        return response()->json(['success' => true, 'data' => $formatted]);
    }

    public function createPoll(Request $request, $conversationId)
    {
        $request->validate([
            'question' => 'required|string',
            'options' => 'required|array|min:2'
        ]);

        $poll = $this->conversationService->createPoll($conversationId, $request->question, $request->options, auth()->id());

        // Create and broadcast poll message
        $message = $this->messageService->sendMessage($conversationId, auth()->id(), (string)$poll->id, 'poll', null);
        $formattedMessage = $this->messageService->formatMessage($message);
        broadcast(new MessageSent($formattedMessage));

        $this->broadcastNewMessageNotification($formattedMessage, $message->conversation, $conversationId);

        return response()->json(['success' => true, 'data' => $poll->load('options')]);
    }

    public function votePoll(Request $request, $pollId)
    {
        $request->validate(['optionId' => 'required']);
        
        try {
            $this->conversationService->votePoll($pollId, $request->optionId, auth()->id());
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // --- Notes ---
    public function getNotes($conversationId)
    {
        $notes = $this->conversationService->getNotes($conversationId);
        
        $formatted = array_map(function($note) {
            return [
                'NoteId' => (string) $note->id,
                'ConversationId' => (string) $note->conversation_id,
                'Content' => $note->content,
                'CreatedByUserId' => (string) $note->created_by,
                'CreatedAt' => $note->created_at,
                'UpdatedAt' => $note->updated_at
            ];
        }, $notes);

        return response()->json(['success' => true, 'data' => $formatted]);
    }

    public function createNote(Request $request, $conversationId)
    {
        $request->validate(['content' => 'required|string']);
        
        $note = $this->conversationService->createNote($conversationId, $request->content, auth()->id());

        // Create and broadcast note message
        $message = $this->messageService->sendMessage($conversationId, auth()->id(), (string)$note->id, 'note', null);
        $formattedMessage = $this->messageService->formatMessage($message);
        broadcast(new MessageSent($formattedMessage));

        $this->broadcastNewMessageNotification($formattedMessage, $message->conversation, $conversationId);

        return response()->json(['success' => true, 'data' => $note]);
    }

    public function updateNote(Request $request, $noteId)
    {
        $request->validate(['content' => 'required|string']);
        
        $this->conversationService->updateNote($noteId, $request->content);

        return response()->json(['success' => true]);
    }

    public function deleteNote($noteId)
    {
        $this->conversationService->deleteNote($noteId);
        return response()->json(['success' => true]);
    }

    private function broadcastNewMessageNotification($formattedMessage, $conversation, $conversationId)
    {
        $chatName = $conversation->is_group 
            ? $conversation->group_name 
            : $formattedMessage['sender']['fullName'];
        
        foreach ($conversation->users as $user) {
            if ($user->id !== auth()->id()) {
                broadcast(new NewMessageNotification(
                    $user->id,
                    (string) $conversationId,
                    [
                        'fullName' => $formattedMessage['sender']['fullName'],
                        'avatar' => $formattedMessage['sender']['avatar'],
                    ],
                    $chatName,
                    $formattedMessage['messageType'] === 'poll' ? 'Đã tạo bình chọn mới' : 'Đã tạo ghi chú mới',
                    $formattedMessage['messageType']
                ))->toOthers();
            }
        }
    }

    private function formatConversation($conv) {
        $users = $conv->users->map(function($u) {
            return [
                '_id' => (string) $u->id,
                'id' => $u->id,
                'fullName' => $u->full_name,
                'email' => $u->email,
                'avatar' => $u->avatar,
            ];
        });

        $latestMessage = null;
        if ($conv->latestMessage) {
            $latestMessage = [
                '_id' => (string) $conv->latestMessage->id,
                'content' => $conv->latestMessage->content,
                'messageType' => $conv->latestMessage->message_type,
                'sender' => $conv->latestMessage->sender ? [
                    '_id' => (string) $conv->latestMessage->sender->id,
                    'fullName' => $conv->latestMessage->sender->full_name
                ] : null,
                'createdAt' => $conv->latestMessage->created_at
            ];
        }

        $chatName = $conv->chat_name;
        $otherUserId = null;
        
        if (!$conv->is_group_chat) {
            $otherUser = $conv->users->where('id', '!=', auth()->id())->first();
            if ($otherUser) {
                $chatName = $otherUser->full_name;
                $otherUserId = (string) $otherUser->id;
            }
        }

        return [
            '_id' => (string) $conv->id,
            'id' => $conv->id,
            'chatName' => $chatName,
            'isGroupChat' => $conv->is_group_chat,
            'users' => $users,
            'latestMessage' => $latestMessage,
            'updatedAt' => $conv->updated_at,
            'otherUserId' => $otherUserId
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\MessageService;
use App\Events\MessageSent;
use App\Events\NewMessageNotification;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    protected $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function getHistory($conversationId)
    {
        // For simple pagination in repository getHistory implementation
        // For now the repository returns array from get().
        $messages = $this->messageService->getHistory($conversationId, 1, 100);

        $formatted = array_map(function($msg) {
            return $this->formatMessage($msg);
        }, $messages);

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    public function searchMessages(Request $request)
    {
        $keyword = $request->query('keyword');
        $conversationId = $request->query('conversationId');

        $messages = $this->messageService->searchMessages($keyword, $conversationId, auth()->id());

        $formatted = array_map(function($m) {
            return $this->formatMessage($m);
        }, $messages);

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'conversationId' => 'required',
            // content could be empty if sending a file
        ]);

        $conversationId = $request->conversationId;
        $content = $request->input('content');
        $messageType = $request->input('messageType', 'text');
        $replyToMessageId = $request->input('replyToMessageId');
        $file = $request->hasFile('file') ? $request->file('file') : null;

        try {
            $message = $this->messageService->sendMessage(
                $conversationId, 
                auth()->id(), 
                $content, 
                $messageType, 
                $replyToMessageId, 
                $file
            );

            $formattedMessage = $this->formatMessage($message);

            // Broadcast event using Laravel Reverb (Pusher)
            broadcast(new MessageSent($formattedMessage))->toOthers();

            // Dispatch global notification to all participants except the sender
            $conversation = $message->conversation;
            $chatName = $conversation->is_group 
                ? $conversation->group_name 
                : $formattedMessage['sender']['fullName']; // Fallback if it's 1-on-1 and we don't calculate the specific friend's name here, the sender's name is usually the chatName from the recipient's perspective.
            
            foreach ($conversation->users as $user) {
                if ($user->id !== auth()->id()) {
                    broadcast(new NewMessageNotification(
                        $user->id,
                        (string) $conversationId,
                        [
                            'fullName' => $formattedMessage['sender']['fullName'],
                            'avatar' => $formattedMessage['sender']['avatar'],
                        ],
                        $chatName, // In 1-on-1, it will show the sender's name, which is correct for the receiver.
                        $content,
                        $messageType
                    ))->toOthers();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $formattedMessage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi gửi tin nhắn: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatMessage($msg) {
        return [
            '_id' => (string) $msg->id,
            'id' => $msg->id,
            'sender' => [
                '_id' => (string) $msg->sender->id,
                'fullName' => $msg->sender->full_name,
                'avatar' => $msg->sender->avatar,
            ],
            'content' => $msg->content,
            'conversationId' => (string) $msg->conversation_id,
            'messageType' => $msg->message_type,
            'fileUrl' => $msg->file_url,
            'fileName' => $msg->file_name,
            'fileSize' => $msg->file_size,
            'mimeType' => $msg->mime_type,
            'isDeletedBySender' => $msg->is_deleted_by_sender,
            'isDeletedForAll' => $msg->is_deleted_for_all,
            'replyToMessageId' => $msg->reply_to_message_id ? (string) $msg->reply_to_message_id : null,
            'readBy' => $msg->readBy->map(function($u) {
                return [
                    '_id' => (string) $u->id,
                    'fullName' => $u->full_name,
                    'avatar' => $u->avatar
                ];
            })->toArray(),
            'createdAt' => $msg->created_at,
            'updatedAt' => $msg->updated_at,
        ];
    }
}

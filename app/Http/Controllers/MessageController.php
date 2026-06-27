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
        // The repository returns ['messages' => [...], 'currentPage' => ..., 'totalPages' => ..., 'totalMessages' => ...]
        $result = $this->messageService->getHistory($conversationId, 1, 100);

        $formatted = array_map(function($msg) {
            return $this->messageService->formatMessage($msg);
        }, $result['messages']);

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $formatted,
                'currentPage' => $result['currentPage'],
                'totalPages' => $result['totalPages'],
                'totalMessages' => $result['totalMessages']
            ]
        ]);
    }

    public function searchMessages(Request $request)
    {
        $keyword = $request->query('keyword');
        $conversationId = $request->query('conversationId');

        $messages = $this->messageService->searchMessages($keyword, $conversationId, auth()->id());

        $formatted = array_map(function($m) {
            return $this->messageService->formatMessage($m);
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

            $formattedMessage = $this->messageService->formatMessage($message);

            // Broadcast event using Laravel Reverb (Pusher)
            broadcast(new MessageSent($formattedMessage));

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

    public function editMessage(Request $request, $messageId)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        try {
            $message = $this->messageService->editMessage($messageId, auth()->id(), $request->input('content'));
            $formattedMessage = $this->messageService->formatMessage($message);

            broadcast(new \App\Events\MessageUpdated($formattedMessage));

            return response()->json([
                'success' => true,
                'data' => $formattedMessage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function recallMessage($messageId)
    {
        try {
            $message = $this->messageService->recallMessage($messageId, auth()->id());
            $formattedMessage = $this->messageService->formatMessage($message);

            broadcast(new \App\Events\MessageDeleted($formattedMessage));

            return response()->json([
                'success' => true,
                'data' => $formattedMessage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

}

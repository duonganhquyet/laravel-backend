<?php

namespace App\Services;

use App\Repositories\FriendRepositoryInterface;
use App\Events\FriendRequestSent;
use App\Events\FriendRequestAccepted;

class FriendService
{
    protected $friendRepository;

    public function __construct(FriendRepositoryInterface $friendRepository)
    {
        $this->friendRepository = $friendRepository;
    }

    public function getFriends($userId)
    {
        return $this->friendRepository->getFriends($userId);
    }

    public function getPendingRequests($userId)
    {
        return $this->friendRepository->getPendingRequests($userId);
    }

    public function getFriendStatus($userId, $targetUserId)
    {
        return $this->friendRepository->getFriendshipStatus($userId, $targetUserId);
    }

    public function sendRequest($userId, $userName, $recipientId)
    {
        if ($userId == $recipientId) {
            throw new \Exception("Không thể kết bạn với chính mình");
        }

        $status = $this->friendRepository->getFriendshipStatus($userId, $recipientId);

        if ($status !== 'none') {
            throw new \Exception("Yêu cầu đã tồn tại hoặc đã là bạn bè");
        }

        $friend = $this->friendRepository->create($userId, $recipientId);

        // Broadcast Realtime FriendRequestSent event
        broadcast(new FriendRequestSent($userId, $userName, $recipientId))->toOthers();

        return $friend;
    }

    public function acceptRequest($userId, $userName, $requestId)
    {
        $friend = $this->friendRepository->findById($requestId);

        if (!$friend || $friend->recipient_id !== $userId) {
            throw new \Exception("Không tìm thấy yêu cầu");
        }

        $friend->update(['status' => 'accepted']);

        // Broadcast Realtime FriendRequestAccepted event
        $requesterId = $friend->requester_id;
        broadcast(new FriendRequestAccepted($userId, $userName, $requesterId))->toOthers();

        return true;
    }

    public function declineRequest($userId, $requestId)
    {
        $friend = $this->friendRepository->findById($requestId);
        
        if ($friend && $friend->recipient_id === $userId) {
            $friend->delete();
            return true;
        }
        return false;
    }

    public function cancelRequest($userId, $recipientId)
    {
        return $this->friendRepository->deleteRequest($userId, $recipientId);
    }

    public function unfriend($userId, $friendId)
    {
        return $this->friendRepository->deleteFriendship($userId, $friendId);
    }
}

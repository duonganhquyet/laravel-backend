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
        if ($userId == $targetUserId) {
            return 'none';
        }

        $friendship = $this->friendRepository->findFriendship($userId, $targetUserId);

        if (!$friendship) {
            return 'none';
        }

        if ($friendship->status === 'accepted') {
            return 'friends';
        }

        if ($friendship->status === 'pending') {
            if ($friendship->requester_id == $userId) {
                return 'pending_sent';
            } else {
                return 'pending_received';
            }
        }

        return 'none';
    }

    public function sendRequest($userId, $userName, $recipientId)
    {
        if ($userId == $recipientId) {
            throw new \Exception("Không thể kết bạn với chính mình");
        }

        $existing = $this->friendRepository->findFriendship($userId, $recipientId);

        if ($existing) {
            throw new \Exception("Yêu cầu đã tồn tại");
        }

        $friend = $this->friendRepository->create([
            'requester_id' => $userId,
            'recipient_id' => $recipientId,
            'status' => 'pending'
        ]);

        // Broadcast Realtime FriendRequestSent event
        broadcast(new FriendRequestSent($userId, $userName, $recipientId))->toOthers();

        return $friend;
    }

    public function acceptRequest($userId, $userName, $requesterId)
    {
        $friend = $this->friendRepository->findPendingRequest($requesterId, $userId);

        if (!$friend) {
            throw new \Exception("Không tìm thấy yêu cầu");
        }

        $this->friendRepository->update($friend->id, ['status' => 'accepted']);

        // Broadcast Realtime FriendRequestAccepted event
        broadcast(new FriendRequestAccepted($userId, $userName, $requesterId))->toOthers();

        return true;
    }

    public function declineRequest($userId, $requesterId)
    {
        $friend = $this->friendRepository->findPendingRequest($requesterId, $userId);

        if ($friend) {
            $this->friendRepository->delete($friend->id);
            return true;
        }

        return false;
    }

    public function cancelRequest($userId, $recipientId)
    {
        $friend = $this->friendRepository->findPendingRequest($userId, $recipientId);

        if ($friend) {
            $this->friendRepository->delete($friend->id);
            return true;
        }

        return false;
    }

    public function unfriend($userId, $friendId)
    {
        $friendship = $this->friendRepository->findFriendship($userId, $friendId);

        if ($friendship) {
            $this->friendRepository->delete($friendship->id);
            return true;
        }

        return false;
    }
}

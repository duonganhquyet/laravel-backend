<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function profile(Request $request)
    {
        $user = auth()->user();
        return response()->json([
            'success' => true,
            'user' => $this->formatUser($user)
        ]);
    }

    public function search(Request $request)
    {
        $keyword = $request->query('keyword', '');
        
        $users = $this->userService->searchUsers($keyword, auth()->id());

        // Transform array of users to formatted array
        $formattedUsers = array_map(function($user) {
            return $this->formatUser($user);
        }, $users);

        return response()->json([
            'success' => true,
            'data' => $formattedUsers
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        try {
            $user = auth()->user();
            
            $user = $this->userService->updateAvatar($user, $request->file('avatar'));

            // The update Avatar might return boolean if updated via repository, so let's refetch if needed.
            // Actually our service returns boolean for update right now, wait let me check UserService! 
            // The service returns the boolean update result, so we should fetch the user again.
            $user = auth()->user()->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật avatar thành công',
                'user' => $this->formatUser($user)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi upload ảnh: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatUser($user) {
        return [
            '_id' => (string) $user->id,
            'id' => $user->id,
            'fullName' => $user->full_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'isVerified' => $user->is_verified,
            'createdAt' => $user->created_at,
            'updatedAt' => $user->updated_at,
        ];
    }
}

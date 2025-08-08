<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
// use App\Services\AttachmentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // public function __construct(private AttachmentsService $attachmentsService) {}

    public function search(Request $request)
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2']
        ]);

        $query = $request->input('query');
        $authUserId = auth()->id();

        $users = User::where('id', '!=', $authUserId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('tag', 'LIKE', "%{$query}%");
            })
            ->limit(10)
            ->get();

        return UserResource::collection($users);
    }

    public function uploadAvatar(Request $request, User $user): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:5000']]);

        $avatarPaths = $user->storeAvatar($request->file('avatar'), $user->id);
        $user->avatar = $avatarPaths;
        $user->save();

        return response()->json([
            'avatar' => [
                'original' => route('api.storage', ['path' => $avatarPaths['original']]),
                'medium' => route('api.storage', ['path' => $avatarPaths['medium']]),
                'small' => route('api.storage', ['path' => $avatarPaths['small']])
            ]
        ]);
    }

    public function deleteAvatar(Request $request, User $user) {}
}

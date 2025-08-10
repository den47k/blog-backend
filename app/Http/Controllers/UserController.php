<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
// use App\Services\AttachmentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // public function __construct(private AttachmentsService $attachmentsService) {}

    public function update(Request $request, User $user): JsonResponse
    {
        if (auth()->id() !== $user->id) abort(403, 'Unauthorized action');

        $request->validate([
            'name' => ['string', 'min:3', 'max:32'],
            'avatar' => ['image', 'max:5000']
        ]);

        $response = [];

        if ($request->has('name')) {
            $user->name = $request->input('name');
            $user->save();
            $response['name'] = $user->name;
        }

        if ($request->hasFile('avatar')) {
            $user->updateAvatar($request->file('avatar'));
            $response['avatar'] = $user->getAvatarUrls();
        }

        return response()->json($response);
    }

    public function deleteAvatar(Request $request, User $user) 
    {
        if (auth()->id() !== $user->id) abort(403, 'Unauthorized action');
        $user->deleteOldAvatar();

        return response()->json(['message' => 'Avatar deleted successfully']);
    }


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
}

<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateAvatarRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function update(UpdateProfileRequest $request, User $user): JsonResponse
    {
        return response()->json(
            $this->userService->updateProfile($user, $request->validated('name'), null)
        );
    }

    public function updateAvatar(UpdateAvatarRequest $request, User $user): JsonResponse
    {
        return response()->json(
            $this->userService->updateProfile($user, null, $request->file('avatar'))
        );
    }

    public function deleteAvatar(Request $request, User $user): JsonResponse
    {
        Gate::authorize('deleteAvatar', $user);

        $this->userService->deleteAvatar($user);

        return response()->json(['message' => 'Avatar deleted successfully']);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        $users = $this->userService->searchUsers(
            $request->input('query'),
            auth()->id(),
        );

        return UserResource::collection($users);
    }
}

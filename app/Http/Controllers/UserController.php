<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
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
    ) {
    }

    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $request->validate([
            'name' => ['string', 'min:3', 'max:32'],
            'avatar' => ['image', 'max:5000'],
        ]);

        $response = $this->userService->updateProfile(
            $user,
            $request->has('name') ? $request->input('name') : null,
            $request->file('avatar'),
        );

        return response()->json($response);
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

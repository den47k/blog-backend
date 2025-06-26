<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
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

<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $authUser, User $user): bool
    {
        return $authUser->id === $user->id;
    }

    public function deleteAvatar(User $authUser, User $user): bool
    {
        return $this->update($authUser, $user);
    }
}

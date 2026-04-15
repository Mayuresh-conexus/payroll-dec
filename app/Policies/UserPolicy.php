<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, User $model): bool
    {
        // Admin can update others, but cannot escalate own role here
        return $user->role === 'admin';
    }

    public function delete(User $user, User $model): bool
    {
        // Admin cannot delete their own account
        return $user->role === 'admin' && $user->id !== $model->id;
    }
}

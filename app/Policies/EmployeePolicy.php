<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->role === 'admin';
    }
}

<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;

class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, PayrollRun $run): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function finalize(User $user, PayrollRun $run): bool
    {
        return $user->role === 'admin';
    }

    public function export(User $user): bool
    {
        return $user->role === 'admin';
    }
}

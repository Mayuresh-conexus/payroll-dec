<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'manager');
    }

    /** Manager can view profile pages only for their assigned employees. */
    public function view(User $user, Employee $employee): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('manager') && $this->managerOwns($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasRole('admin');
    }

    /** Admin sees all attendance; manager only sees their assigned employees. */
    public function viewAttendance(User $user, Employee $employee): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('manager') && $this->managerOwns($user, $employee);
    }

    /** Same scope as viewAttendance — manager can only edit what they can see. */
    public function editAttendance(User $user, Employee $employee): bool
    {
        return $this->viewAttendance($user, $employee);
    }

    private function managerOwns(User $manager, Employee $employee): bool
    {
        return $manager->assignedEmployees()
            ->where('employees.id', $employee->id)
            ->exists();
    }
}

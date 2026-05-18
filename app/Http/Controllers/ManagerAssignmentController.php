<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignEmployeesRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ManagerAssignmentController extends Controller
{
    public function index(): View
    {
        $managers = User::where('role', 'manager')
            ->with(['assignedEmployees'])
            ->orderBy('name')
            ->get();

        $allEmployees = Employee::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Map employee_id → manager info so the view can show "Assigned to [Name]" hints
        $assignmentMap = DB::table('manager_employee')
            ->join('users', 'manager_employee.manager_id', '=', 'users.id')
            ->select('manager_employee.employee_id', 'users.name as manager_name', 'manager_employee.manager_id')
            ->get()
            ->keyBy('employee_id');

        return view('managers.index', compact('managers', 'allEmployees', 'assignmentMap'));
    }

    public function assign(AssignEmployeesRequest $request, User $manager): RedirectResponse
    {
        abort_unless($manager->hasRole('manager'), 422, 'The selected user is not a manager.');

        $employeeIds = $request->validated()['employee_ids'] ?? [];

        // Detect employees already assigned to a DIFFERENT manager
        $conflicts = DB::table('manager_employee')
            ->whereIn('employee_id', $employeeIds)
            ->where('manager_id', '!=', $manager->id)
            ->pluck('employee_id');

        if ($conflicts->isNotEmpty()) {
            return back()->withErrors([
                'employee_ids' => 'One or more employees are already assigned to a different manager.',
            ]);
        }

        // Build pivot data with metadata and sync (replaces previous assignments for this manager)
        $syncData = collect($employeeIds)->mapWithKeys(fn ($id) => [
            $id => ['assigned_by' => auth()->id(), 'assigned_at' => now()],
        ])->all();

        $manager->assignedEmployees()->sync($syncData);

        return back()->with('success', "Team updated for {$manager->name}.");
    }

    public function unassign(User $manager, Employee $employee): RedirectResponse
    {
        abort_unless($manager->hasRole('manager'), 422, 'The selected user is not a manager.');

        $manager->assignedEmployees()->detach($employee->id);

        return back()->with('success', "{$employee->name} removed from {$manager->name}'s team.");
    }
}

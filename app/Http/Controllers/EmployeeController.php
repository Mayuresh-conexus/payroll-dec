<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdateManagerAccessRequest;
use App\Models\AuditLog;
use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\EmployeeRate;
use App\Models\HourlyAttendance;
use App\Models\PayrollItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $query = Employee::orderBy('id', 'desc');

        // MISSING-03: Search and filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active' ? 1 : 0);
        }

        // Eager-load the latest rate of each type so the table always reflects
        // the most recent entry in employee_rates (the source of truth for rate history),
        // not just the denormalized column on the employees table.
        $employees = $query->with([
            'rates' => fn ($q) => $q->orderByDesc('effective_from')->orderByDesc('id'),
            'user',
        ])->paginate(10)->withQueryString();

        return view('employees.index', compact('employees'));
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);

        // Rate history, newest first
        $rateHistory = $employee->rates()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        // Resolve creator names in one query rather than N
        $creators = User::whereIn('id', $rateHistory->pluck('created_by')->filter()->unique())
            ->pluck('name', 'id');

        // Audit trail for this employee record (most recent 25 entries)
        $auditLogs = AuditLog::where('model_type', 'Employee')
            ->where('model_id', $employee->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        // Last 8 ISO weeks attendance, bulk-fetched in one query per type
        $attendanceHistory = $this->buildAttendanceHistory($employee, 8);

        // Last 6 payroll items with their run metadata
        $payrollHistory = PayrollItem::where('employee_id', $employee->id)
            ->with('payrollRun')
            ->whereHas('payrollRun')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('employees.show', compact(
            'employee',
            'rateHistory',
            'creators',
            'auditLogs',
            'attendanceHistory',
            'payrollHistory',
        ));
    }

    private function buildAttendanceHistory(Employee $employee, int $weeks): Collection
    {
        $today = now();
        $weekRefs = collect(range(0, $weeks - 1))->map(fn ($i) => [
            'year' => $today->copy()->subWeeks($i)->isoWeekYear,
            'week' => $today->copy()->subWeeks($i)->isoWeek,
        ]);

        if ($employee->type === 'daily_rate') {
            $records = DailyRateAttendance::where('employee_id', $employee->id)
                ->where(function ($q) use ($weekRefs) {
                    foreach ($weekRefs as $ref) {
                        $q->orWhere(fn ($q2) => $q2->where('year', $ref['year'])->where('week_number', $ref['week']));
                    }
                })->get()->keyBy(fn ($a) => "{$a->year}-{$a->week_number}");

            return $weekRefs->map(function ($ref) use ($records) {
                $att = $records->get("{$ref['year']}-{$ref['week']}");

                return [
                    'year' => $ref['year'],
                    'week' => $ref['week'],
                    'type' => 'daily',
                    'marked' => (bool) $att,
                    'present' => $att?->present_days ?? 0,
                    'total' => $att?->total_working_days ?? 0,
                    'overtime' => $att?->overtime_amount ?? 0,
                    'locked' => $att?->locked ?? false,
                ];
            });
        }

        $records = HourlyAttendance::where('employee_id', $employee->id)
            ->where(function ($q) use ($weekRefs) {
                foreach ($weekRefs as $ref) {
                    $q->orWhere(fn ($q2) => $q2->where('year', $ref['year'])->where('week_number', $ref['week']));
                }
            })->get()->keyBy(fn ($a) => "{$a->year}-{$a->week_number}");

        return $weekRefs->map(function ($ref) use ($records) {
            $att = $records->get("{$ref['year']}-{$ref['week']}");

            return [
                'year' => $ref['year'],
                'week' => $ref['week'],
                'type' => 'hourly',
                'marked' => (bool) $att,
                'total_hours' => $att?->total_hours ?? 0,
                'overtime_hours' => $att?->overtime_hours ?? 0,
                'locked' => $att?->locked ?? false,
            ];
        });
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        $rateHistory = $employee->rates()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $ratesByType = $rateHistory->groupBy('rate_type');

        $creators = User::whereIn('id', $rateHistory->pluck('created_by')->filter()->unique())
            ->pluck('name', 'id');

        $rateTypeTabs = $employee->type === 'daily_rate'
            ? [['key' => 'daily_rate', 'label' => 'Daily Rate']]
            : [
                ['key' => 'hourly_rate', 'label' => 'Hourly Rate'],
                ['key' => 'hours_per_day', 'label' => 'Hours / Day'],
            ];

        // Employee-level status events, shown alongside rate changes in the timeline.
        $statusChanges = $employee->statusChanges()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $creators = $creators->union(
            User::whereIn('id', $statusChanges->pluck('created_by')->filter()->unique())
                ->pluck('name', 'id')
        );

        // Manager access panel: the linked login (if any), the team they already
        // look after, and the pool of employees not yet claimed by any manager.
        $managerUser = $employee->user()->first();
        $assignedEmployeeIds = $managerUser
            ? $managerUser->assignedEmployees()->pluck('employees.id')->all()
            : [];

        $selectableEmployees = Employee::where('is_active', true)
            ->whereKeyNot($employee->id)
            ->where(function ($q) use ($assignedEmployeeIds) {
                $q->whereDoesntHave('managers');
                if ($assignedEmployeeIds !== []) {
                    $q->orWhereIn('id', $assignedEmployeeIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'department']);

        return view('employees.edit', compact(
            'employee', 'ratesByType', 'creators', 'rateTypeTabs', 'statusChanges',
            'managerUser', 'assignedEmployeeIds', 'selectableEmployees',
        ));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        // create employee record
        $employee = Employee::create($data);

        // create initial employee_rates if provided
        $effectiveFrom = $request->input('rate_effective_from', now()->toDateString());
        if (isset($data['daily_rate']) && $data['daily_rate'] !== null) {
            EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => 'daily_rate',
                'amount' => $data['daily_rate'],
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }
        if (isset($data['hourly_rate']) && $data['hourly_rate'] !== null) {
            EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => 'hourly_rate',
                'amount' => $data['hourly_rate'],
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }
        if (isset($data['hours_per_day']) && $data['hours_per_day'] !== null) {
            EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => 'hours_per_day',
                'amount' => $data['hours_per_day'],
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }

        // Create or link manager user account if requested
        if ($request->boolean('grant_manager_access') && ($data['manager_email'] ?? null)) {
            $existingUser = User::where('email', $data['manager_email'])->first();

            if ($existingUser) {
                // Link the existing unlinked manager account to this employee
                $existingUser->employee_id = $employee->id;
                $existingUser->save();
            } else {
                if (empty($data['manager_password'])) {
                    return back()->withErrors(['manager_password' => 'A password is required when creating a new manager account.'])->withInput();
                }
                User::create([
                    'name' => $employee->name,
                    'email' => $data['manager_email'],
                    'password' => bcrypt($data['manager_password']),
                    'role' => 'manager',
                    'employee_id' => $employee->id,
                ]);
            }
        }

        return back()->with('success', 'Employee created successfully');
    }

    public function update(UpdateEmployeeRequest $request, $id)
    {
        $employee = Employee::findOrFail($id);

        if ($request->input('type') !== $employee->type) {
            return back()->withErrors(['type' => 'Employee type cannot be changed.']);
        }

        $data = $request->validated();

        // Status is changed through its own endpoint, never as a side effect of
        // saving the form. Without this guard a form that omits the field would
        // read as "unchecked" and silently deactivate the employee.
        unset($data['is_active'], $data['deactivated_at']);

        // Fall back to today only when the field is absent or blank — the middleware
        // converts an empty submitted value to null, which would otherwise be stored
        // as a null effective_from and silently rank last in every rate lookup.
        $effectiveFrom = $request->input('rate_effective_from') ?: now()->toDateString();

        // Detect rate changes and append EmployeeRate history rows rather than
        // silently overwriting history.
        foreach (['daily_rate', 'hourly_rate', 'hours_per_day'] as $rateType) {
            if (! array_key_exists($rateType, $data)) {
                continue;
            }

            if (! $this->rateValueChanged($data[$rateType], $employee->{$rateType})) {
                continue;
            }

            EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => $rateType,
                'amount' => $data[$rateType] ?? 0,
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }

        // still update the employee table for convenience (UI, defaults)
        $employee->update($data);

        // Keep the denormalized rate columns in step with the rate actually in effect.
        // A backdated entry must not leave the column advertising a value that
        // latestRateOf()/rateAt() would never return (both order by effective_from).
        $this->syncDenormalizedRates($employee);

        // Manager access is handled by updateManagerAccess() so an ordinary save
        // can never create, alter or revoke someone's login.

        return back()->with('success', 'Employee updated successfully');
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        $employee->delete(); // Soft delete — deleted_at is set; record is retained in DB

        return back()->with('success', 'Employee deleted successfully');
    }

    /**
     * Compare a submitted rate against the stored one numerically.
     *
     * A strict !== comparison here would treat the form string "450" and the DB
     * decimal string "450.00" as different, appending a bogus history row on every
     * single save even when nothing changed.
     */
    private function rateValueChanged(mixed $new, mixed $old): bool
    {
        if ($new === null && $old === null) {
            return false;
        }

        if ($new === null || $old === null) {
            return true;
        }

        return abs((float) $new - (float) $old) > 0.00001;
    }

    /**
     * Point each denormalized rate column at the most recent history entry for that
     * type, so the column always agrees with latestRateOf()/rateAt().
     */
    private function syncDenormalizedRates(Employee $employee): void
    {
        $updates = [];

        foreach (['daily_rate', 'hourly_rate', 'hours_per_day'] as $rateType) {
            $latest = EmployeeRate::where('employee_id', $employee->id)
                ->where('rate_type', $rateType)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($latest) {
                $updates[$rateType] = $latest->amount;
            }
        }

        if ($updates !== []) {
            $employee->update($updates);
        }
    }

    /**
     * Activate or deactivate an employee from its own control, with a chosen
     * effective date that drives the payroll cut-off.
     */
    public function updateStatus(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
            // Future dates are rejected: access is revoked the moment this is saved,
            // so pay must not be promised beyond that point.
            'effective_from' => 'required|date|before_or_equal:today',
        ], [
            'effective_from.before_or_equal' => 'The effective date cannot be in the future.',
        ]);

        $isActive = (bool) $validated['is_active'];
        $effectiveFrom = $validated['effective_from'];

        if ($isActive === (bool) $employee->is_active) {
            return back()->with('success', 'Employment status is already up to date.');
        }

        $employee->update([
            'is_active' => $isActive,
            'deactivated_at' => $isActive ? null : $effectiveFrom,
        ]);

        $employee->statusChanges()->create([
            'is_active' => $isActive,
            'effective_from' => $effectiveFrom,
            'created_by' => auth()->id(),
        ]);

        return back()->with(
            'success',
            $isActive
                ? 'Employee reactivated.'
                : 'Employee deactivated with effect from '.Carbon::parse($effectiveFrom)->format('d M Y').'.'
        );
    }

    /**
     * Grant, update or revoke manager login access, together with the team the
     * manager looks after. Handled here rather than in the employee form so a
     * normal save can never touch someone's access.
     */
    public function updateManagerAccess(UpdateManagerAccessRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $linkedUser = $employee->user()->first();

        if ($request->boolean('revoke')) {
            if ($linkedUser) {
                // Pivot rows cascade with the user, releasing the team back to the pool.
                $linkedUser->delete();
            }

            return back()->with('success', 'Manager access revoked.');
        }

        $data = $request->validated();

        // Refuse employees already claimed by a different manager rather than
        // silently stealing them. Checked BEFORE touching the account, otherwise a
        // rejected request would still leave a freshly created login with no team.
        $conflicts = Employee::whereIn('id', $data['employee_ids'])
            ->whereHas('managers', fn ($q) => $q->when($linkedUser, fn ($inner) => $inner->where('users.id', '!=', $linkedUser->id)))
            ->pluck('name');

        if ($conflicts->isNotEmpty()) {
            return back()->withErrors([
                'employee_ids' => 'Already assigned to another manager: '.$conflicts->implode(', ').'.',
            ])->withInput();
        }

        if ($linkedUser) {
            $linkedUser->email = $data['email'];
            if (! empty($data['password'])) {
                $linkedUser->password = bcrypt($data['password']);
            }
            $linkedUser->role = 'manager';
            $linkedUser->save();
            $manager = $linkedUser;
        } else {
            $manager = User::create([
                'name' => $employee->name,
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'role' => 'manager',
                'employee_id' => $employee->id,
            ]);
        }

        $manager->assignedEmployees()->sync(
            collect($data['employee_ids'])
                ->mapWithKeys(fn ($id) => [$id => ['assigned_by' => auth()->id(), 'assigned_at' => now()]])
                ->all()
        );

        return back()->with('success', 'Manager access saved with '.count($data['employee_ids']).' assigned employee(s).');
    }

    public function destroyRate(Employee $employee, EmployeeRate $rate)
    {
        $this->authorize('update', $employee);

        abort_unless($rate->employee_id === $employee->id, 404);

        $rate->delete(); // soft delete — Auditable trait logs it automatically

        $latestRemaining = EmployeeRate::where('employee_id', $employee->id)
            ->where('rate_type', $rate->rate_type)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($latestRemaining) {
            $employee->update([$rate->rate_type => $latestRemaining->amount]);
        }

        return back()->with('success', 'Rate history entry deleted.');
    }
}

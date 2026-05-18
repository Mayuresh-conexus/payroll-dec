<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\AuditLog;
use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\PayrollItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        // create employee record
        $employee = Employee::create($data);

        // create initial employee_rates if provided
        $effectiveFrom = $request->input('rate_effective_from', now()->toDateString());
        if (isset($data['daily_rate']) && $data['daily_rate'] !== null) {
            \App\Models\EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => 'daily_rate',
                'amount' => $data['daily_rate'],
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }
        if (isset($data['hourly_rate']) && $data['hourly_rate'] !== null) {
            \App\Models\EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => 'hourly_rate',
                'amount' => $data['hourly_rate'],
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }
        if (isset($data['hours_per_day']) && $data['hours_per_day'] !== null) {
            \App\Models\EmployeeRate::create([
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

        // force boolean from checkbox 0 or 1
        $data['is_active'] = $request->boolean('is_active');

        // detect rate changes and create EmployeeRate entries instead of silently overwriting history
        $effectiveFrom = $request->input('rate_effective_from', now()->toDateString());

        // daily_rate
        if (array_key_exists('daily_rate', $data) && $data['daily_rate'] !== $employee->daily_rate) {
            \App\Models\EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => 'daily_rate',
                'amount' => $data['daily_rate'] ?? 0,
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }

        // hourly_rate
        if (array_key_exists('hourly_rate', $data) && $data['hourly_rate'] !== $employee->hourly_rate) {
            \App\Models\EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => 'hourly_rate',
                'amount' => $data['hourly_rate'] ?? 0,
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }

        // hours_per_day
        if (array_key_exists('hours_per_day', $data) && $data['hours_per_day'] !== $employee->hours_per_day) {
            \App\Models\EmployeeRate::create([
                'employee_id' => $employee->id,
                'rate_type' => 'hours_per_day',
                'amount' => $data['hours_per_day'] ?? 0,
                'effective_from' => $effectiveFrom,
                'created_by' => auth()->id(),
            ]);
        }

        // still update the employee table for convenience (UI, defaults)
        $employee->update($data);

        // Handle manager user account changes
        $linkedUser = $employee->user()->first();

        if ($request->boolean('revoke_manager_access') && $linkedUser) {
            $linkedUser->delete();
        } elseif ($request->boolean('grant_manager_access') && ! $linkedUser && ($data['manager_email'] ?? null)) {
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
        } elseif ($linkedUser && ($data['manager_email'] ?? null)) {
            $linkedUser->email = $data['manager_email'];
            if (! empty($data['manager_password'])) {
                $linkedUser->password = bcrypt($data['manager_password']);
            }
            $linkedUser->save();
        }

        return back()->with('success', 'Employee updated successfully');
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        $employee->delete(); // Soft delete — deleted_at is set; record is retained in DB

        return back()->with('success', 'Employee deleted successfully');
    }

    /**
     * Return JSON rate history for given employee.
     */
    public function rates($id)
    {
        $employee = Employee::findOrFail($id);

        $rates = \App\Models\EmployeeRate::where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'rate_type' => $r->rate_type,
                    'amount' => (float) $r->amount,
                    'effective_from' => $r->effective_from,
                    'effective_to' => $r->effective_to,
                    'created_by' => $r->created_by,
                    'created_by_name' => $r->created_by ? optional(\App\Models\User::find($r->created_by))->name : null,
                    'created_at' => $r->created_at ? $r->created_at->toDateTimeString() : null,
                ];
            });

        return response()->json(['data' => $rates]);
    }
}

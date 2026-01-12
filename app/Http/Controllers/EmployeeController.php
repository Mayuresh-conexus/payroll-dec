<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::orderBy('id', 'desc')->paginate(10);
        return view('employees.index', compact('employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_code' => 'required|string|max:50|unique:employees,employee_code',
            'name'          => 'required|string|max:255',
            'joining_date'  => 'nullable|date',
            'department'    => 'nullable|string|max:100',
            'type'          => 'required|in:daily_rate,hourly',
            'daily_rate'    => 'required_if:type,daily_rate|nullable|numeric',
            'hourly_rate'   => 'required_if:type,hourly|nullable|numeric',
            'hours_per_day' => 'required_if:type,hourly|nullable|numeric',
        ]);

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

        return back()->with('success', 'Employee created successfully');
    }

   public function update(Request $request, $id)
{
    $employee = Employee::findOrFail($id);

    $data = $request->validate([
        'employee_code' => 'required|string|max:50|unique:employees,employee_code,' . $employee->id,
        'name'          => 'required|string|max:255',
        'joining_date'  => 'nullable|date',
        'department'    => 'nullable|string|max:100',
        // lock type to the existing value on the employee record
        'type'          => 'required|in:' . $employee->type,
        'daily_rate'    => 'required_if:type,daily_rate|nullable|numeric',
        'hourly_rate'   => 'required_if:type,hourly|nullable|numeric',
        'hours_per_day' => 'required_if:type,hourly|nullable|numeric',
        'is_active'     => 'nullable|boolean',
    ]);

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

    return back()->with('success', 'Employee updated successfully');
}



    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        // If you want soft delete, ensure model uses SoftDeletes
        // $employee->delete();

        $employee->delete(); // hard delete

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

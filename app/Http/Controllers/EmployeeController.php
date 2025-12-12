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
            'daily_rate'    => 'nullable|numeric',
            'hourly_rate'   => 'nullable|numeric',
            'hours_per_day' => 'nullable|numeric',
        ]);

        Employee::create($data);

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
        'type'          => 'required|in:daily_rate,hourly',
        'daily_rate'    => 'nullable|numeric',
        'hourly_rate'   => 'nullable|numeric',
        'hours_per_day' => 'nullable|numeric',
        'is_active'     => 'nullable|boolean',
    ]);

    // force boolean from checkbox 0 or 1
    $data['is_active'] = $request->boolean('is_active');

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
}

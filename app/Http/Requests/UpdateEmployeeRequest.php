<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin']);
    }

    public function rules(): array
    {
        $emp = $this->route('employee');
        $employeeId = is_object($emp) ? $emp->id : $emp;
        $employee = is_object($emp) ? $emp : \App\Models\Employee::find($employeeId);
        $linkedUserId = $employee?->user?->id;

        return [
            'employee_code' => 'required|string|max:50|unique:employees,employee_code,'.$employeeId,
            'name' => 'required|string|max:255',
            'joining_date' => 'nullable|date',
            'department' => 'nullable|string|max:100',
            'type' => 'required|string', // locked to existing value — checked in controller
            'daily_rate' => 'required_if:type,daily_rate|nullable|numeric|min:0',
            'hourly_rate' => 'required_if:type,hourly|nullable|numeric|min:0',
            'hours_per_day' => 'required_if:type,hourly|nullable|numeric|min:0',
            'bank_transfer_fix_amount' => 'nullable|numeric|min:0',
            'weekly_active_days' => 'nullable|integer|min:1|max:7',
            'is_active' => 'nullable|boolean',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_ifsc' => 'nullable|string|max:20',
            'rate_effective_from' => 'nullable|date',
            'grant_manager_access' => 'nullable|boolean',
            'manager_email' => [
                'required_if:grant_manager_access,1',
                'nullable',
                'email',
                // Ignore the currently linked user's email; only block admins or managers linked to another employee
                Rule::unique('users', 'email')
                    ->ignore($linkedUserId)
                    ->where(fn ($q) => $q->where('role', 'admin')->orWhereNotNull('employee_id')),
            ],
            'manager_password' => 'nullable|string|min:8|confirmed',
            'revoke_manager_access' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_code.unique' => 'This employee code is already taken by another employee.',
            'daily_rate.required_if' => 'Daily rate is required for daily-rate employees.',
            'hourly_rate.required_if' => 'Hourly rate is required for hourly employees.',
            'hours_per_day.required_if' => 'Hours per day is required for hourly employees.',
            'manager_email.unique' => 'This email belongs to an account that is already linked to another employee.',
        ];
    }
}

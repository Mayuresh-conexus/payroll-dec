<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin']);
    }

    public function rules(): array
    {
        return [
            'employee_code' => 'required|string|max:50|unique:employees,employee_code',
            'name' => 'required|string|max:255',
            'joining_date' => 'nullable|date',
            'department' => 'nullable|string|max:100',
            'type' => 'required|in:daily_rate,hourly',
            'daily_rate' => 'required_if:type,daily_rate|nullable|numeric|min:0',
            'hourly_rate' => 'required_if:type,hourly|nullable|numeric|min:0',
            'hours_per_day' => 'required_if:type,hourly|nullable|numeric|min:0',
            'bank_transfer_fix_amount' => 'nullable|numeric|min:0',
            'weekly_active_days' => 'nullable|integer|min:1|max:7',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_ifsc' => 'nullable|string|max:20',
            'rate_effective_from' => 'nullable|date',
            'grant_manager_access' => 'nullable|boolean',
            'manager_email' => [
                'required_if:grant_manager_access,1',
                'nullable',
                'email',
                // Allow linking an existing unlinked manager; only block admins or already-linked managers
                Rule::unique('users', 'email')->where(
                    fn ($q) => $q->where('role', 'admin')->orWhereNotNull('employee_id')
                ),
            ],
            'manager_password' => 'nullable|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_code.unique' => 'This employee code is already taken.',
            'daily_rate.required_if' => 'Daily rate is required for daily-rate employees.',
            'hourly_rate.required_if' => 'Hourly rate is required for hourly employees.',
            'hours_per_day.required_if' => 'Hours per day is required for hourly employees.',
            'manager_email.unique' => 'This email belongs to an account that is already linked to another employee.',
        ];
    }
}

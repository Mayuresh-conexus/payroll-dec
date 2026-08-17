<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin']);
    }

    /**
     * Employment status and manager access are deliberately absent — both are
     * changed through their own endpoints so an ordinary save cannot affect them.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $emp = $this->route('employee');
        $employeeId = is_object($emp) ? $emp->id : $emp;

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
            'bh_bank_percent' => 'nullable|numeric|min:0|max:100',
            'weekly_active_days' => 'nullable|integer|min:1|max:7',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_ifsc' => 'nullable|string|max:20',
            'rate_effective_from' => 'nullable|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_code.unique' => 'This employee code is already taken by another employee.',
            'daily_rate.required_if' => 'Daily rate is required for daily-rate employees.',
            'hourly_rate.required_if' => 'Hourly rate is required for hourly employees.',
            'hours_per_day.required_if' => 'Hours per day is required for hourly employees.',
        ];
    }
}

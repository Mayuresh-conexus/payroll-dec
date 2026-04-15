<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared FormRequest for all three attendance store methods:
 * storeDailyRate, storeHourly, storeCombined.
 */
class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'year'                        => 'required|integer|min:2020|max:2099',
            'week'                        => 'required|integer|min:1|max:53',
            'lock_week'                   => 'nullable|boolean',
            'attendance'                  => 'required|array',
            // daily fields
            'attendance.*.days'           => 'nullable|array',
            'attendance.*.days.*'         => 'in:0,1',
            'attendance.*.overtime_map'   => 'nullable|array',
            'attendance.*.overtime_map.*' => 'nullable|numeric|min:0',
            // hourly fields
            'attendance.*.hours_map'      => 'nullable|array',
            'attendance.*.hours_map.*'    => 'nullable|numeric|min:0',
            'attendance.*.ot_map'         => 'nullable|array',
            'attendance.*.ot_map.*'       => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'week.max' => 'Week number cannot exceed 53.',
            'week.min' => 'Week number must be at least 1.',
        ];
    }
}

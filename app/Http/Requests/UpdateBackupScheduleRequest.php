<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            'frequency' => 'required|in:daily,weekly',
            'run_at' => 'required|date_format:H:i',
            'day_of_week' => 'required_if:frequency,weekly|nullable|integer|between:0,6',
            'retention_days' => 'nullable|integer|min:1|max:365',
        ];
    }
}

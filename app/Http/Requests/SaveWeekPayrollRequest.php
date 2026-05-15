<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWeekPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * Normalize items array before validation — copy employee.id into employee_id
     * when the JS sends the full employee object instead of just the id.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);

        foreach ($items as $k => $it) {
            if (empty($it['employee_id'])) {
                if (! empty($it['employee']['id'])) {
                    $items[$k]['employee_id'] = $it['employee']['id'];
                }
            }
        }

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:2020|max:2099',
            'week' => 'required|integer|min:1|max:53',
            'items' => 'required|array',
            'items.*.employee_id' => 'required|integer|exists:employees,id',
            'items.*.type' => 'required|in:daily_rate,hourly',
            'items.*.total_days' => 'nullable|integer|min:0',
            'items.*.present_days' => 'nullable|integer|min:0',
            'items.*.total_hours' => 'nullable|numeric|min:0',
            'items.*.overtime' => 'nullable|numeric|min:0',
            'items.*.cash' => 'nullable|numeric|min:0',
            'items.*.bank' => 'nullable|numeric|min:0',  // may exceed weekly when advance given
            'items.*.weekly_amount' => 'nullable|numeric|min:0',
            'items.*.prev_advance_balance' => 'nullable|numeric|min:0',
            'items.*.bank_transfer_fix_amount' => 'nullable|numeric|min:0',
            'items.*.recover' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'week.max' => 'Week number cannot exceed 53.',
            'items.*.employee_id.exists' => 'Employee not found.',
        ];
    }
}

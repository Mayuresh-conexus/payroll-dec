<?php

namespace App\Http\Requests;

use App\Models\Leave;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * Leave entered with only a start date is a single day, so mirror the start
     * into the end date rather than making the second field mandatory.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('end_date') && $this->filled('start_date')) {
            $this->merge(['end_date' => $this->input('start_date')]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'hours_per_day' => 'nullable|numeric|min:0|max:24',
            'reason' => 'nullable|string|max:255',
        ];
    }

    /**
     * Two records covering one day would deduct it from the balance twice, so
     * overlapping leave is rejected rather than quietly merged. The record being
     * edited is excluded — it always overlaps itself.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clash = Leave::query()
                ->where('employee_id', $this->input('employee_id'))
                ->whereKeyNot($this->route('leave')->getKey())
                ->overlapping($this->date('start_date'), $this->date('end_date'))
                ->first();

            if ($clash) {
                $validator->errors()->add(
                    'start_date',
                    'This employee already has leave from '
                        .$clash->start_date->format('d M Y').' to '.$clash->end_date->format('d M Y').'.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Choose the employee taking leave.',
            'start_date.required' => 'Choose the date the leave starts.',
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
            'hours_per_day.max' => 'A day of leave cannot be longer than 24 hours.',
        ];
    }
}

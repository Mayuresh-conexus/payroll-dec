<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * A holiday entered with only a start date is a single day, so mirror the
     * start into the end date rather than making the second field mandatory.
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
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the holiday a name, for example "St Patrick\'s Day".',
            'start_date.required' => 'Choose the date the holiday starts.',
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagerAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $employee = $employee instanceof Employee ? $employee : Employee::find($employee);
        $linkedUserId = $employee?->user?->id;

        // Revoking needs no credentials or team.
        if ($this->boolean('revoke')) {
            return ['revoke' => 'required|boolean'];
        }

        return [
            'revoke' => 'nullable|boolean',
            'email' => [
                'required', 'email',
                Rule::unique('users', 'email')->ignore($linkedUserId),
            ],
            // Required only when there is no account yet — otherwise blank keeps the current one.
            'password' => [$linkedUserId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            // A manager must look after at least one person.
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Select at least one employee for this manager to look after.',
            'employee_ids.min' => 'Select at least one employee for this manager to look after.',
            'email.unique' => 'This email is already used by another account.',
            'password.required' => 'A password is required when creating a new manager account.',
        ];
    }
}

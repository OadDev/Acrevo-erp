<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'designation' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'employment_type' => ['required', 'in:permanent,contract,daily_wage'],
            'joining_date' => ['nullable', 'date'],
            'salary_type' => ['required', 'in:monthly,daily,hourly'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}

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
            'date_of_birth' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'designation' => ['nullable', 'string', 'max:150'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_summary' => ['nullable', 'string'],
            'skill_set' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'employment_type' => ['required', 'in:permanent,contract,daily_wage'],
            'joining_date' => ['nullable', 'date'],
            'salary_type' => ['required', 'in:monthly,daily,hourly'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ];
    }
}

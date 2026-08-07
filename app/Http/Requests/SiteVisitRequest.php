<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enquiry_id' => ['required', 'exists:enquiries,id'],
            'scheduled_at' => ['required', 'date'],
            'assigned_to' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

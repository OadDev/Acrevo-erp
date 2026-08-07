<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quotation_id' => ['nullable', 'exists:quotations,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

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
            'quotation_id' => ['required', 'exists:quotations,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'execution_way' => ['required', 'in:'.implode(',', array_keys(\App\Models\WorkOrder::EXECUTION_WAYS))],
            'executive_team_id' => ['nullable', 'exists:executive_teams,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'site_city' => ['nullable', 'string', 'max:255'],
            'site_state' => ['nullable', 'string', 'max:255'],
            'site_pincode' => ['nullable', 'string', 'max:20'],
            'site_contact_name' => ['nullable', 'string', 'max:255'],
            'site_contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}

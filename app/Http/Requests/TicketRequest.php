<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_order_id' => ['required', 'exists:work_orders,id'],
            'type' => ['required', 'in:delay,material,client_change,quality,safety,technical,finance,internal'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}

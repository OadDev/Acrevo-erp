<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Budget fields are often typed or pasted with thousands separators
     * (e.g. "50,000") - strip them so a comma doesn't fail the 'numeric'
     * rule and silently drop the whole submission.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_material_budget' => is_string($this->estimated_material_budget)
                ? str_replace(',', '', $this->estimated_material_budget)
                : $this->estimated_material_budget,
            'estimated_labour_budget' => is_string($this->estimated_labour_budget)
                ? str_replace(',', '', $this->estimated_labour_budget)
                : $this->estimated_labour_budget,
        ]);
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
            'team_leader_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_material_budget' => ['nullable', 'numeric', 'min:0'],
            'estimated_labour_budget' => ['nullable', 'numeric', 'min:0'],
            'materials' => ['nullable', 'array'],
            'materials.*.material_name' => ['nullable', 'string', 'max:255'],
            'materials.*.brand' => ['nullable', 'string', 'max:150'],
            'materials.*.size' => ['nullable', 'string', 'max:100'],
            'materials.*.unit' => ['nullable', 'string', 'max:30'],
            'materials.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'materials.*.rate' => ['nullable', 'numeric', 'min:0'],
            'materials.*.vendor' => ['nullable', 'string', 'max:255'],
            'labour' => ['nullable', 'array'],
            'labour.*.labour_type' => ['nullable', 'string', 'max:150'],
            'labour.*.count' => ['nullable', 'integer', 'min:1'],
            'labour.*.hours' => ['nullable', 'numeric', 'min:0'],
            'labour.*.wage_rate' => ['nullable', 'numeric', 'min:0'],
            'time_schedules' => ['nullable', 'array'],
            'time_schedules.*.time_to_finish' => ['nullable', 'string', 'max:100'],
            'time_schedules.*.unit' => ['nullable', 'string', 'max:50'],
            'time_schedules.*.remark' => ['nullable', 'string', 'max:255'],
            'procedures' => ['nullable', 'array'],
            'procedures.*.item_description' => ['nullable', 'string', 'max:255'],
            'procedures.*.length' => ['nullable', 'numeric', 'min:0'],
            'procedures.*.breadth' => ['nullable', 'numeric', 'min:0'],
            'procedures.*.height' => ['nullable', 'numeric', 'min:0'],
            'procedures.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'procedures.*.unit' => ['nullable', 'string', 'max:30'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'site_city' => ['nullable', 'string', 'max:255'],
            'site_state' => ['nullable', 'string', 'max:255'],
            'site_pincode' => ['nullable', 'string', 'max:20'],
            'site_contact_name' => ['nullable', 'string', 'max:255'],
            'site_contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}

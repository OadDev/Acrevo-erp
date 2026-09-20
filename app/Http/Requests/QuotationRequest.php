<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Editing an existing quotation (PUT /quotations/{quotation}) never
        // changes which enquiry/client it belongs to - update() doesn't even
        // read these fields - so the edit form doesn't submit them. Only
        // creating a new quotation (POST /quotations, no route model bound)
        // needs them.
        $isUpdate = $this->route('quotation') !== null;

        return [
            'enquiry_id' => [$isUpdate ? 'nullable' : 'required', 'exists:enquiries,id'],
            'client_id' => [$isUpdate ? 'nullable' : 'required', 'exists:clients,id'],
            'discount_type' => ['required', 'in:flat,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string'],
            'valid_until' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:product,service'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

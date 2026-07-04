<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SponsorshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:36'],
            'first_due_date' => ['nullable', 'date'],
            'due_dates' => ['nullable', 'array', 'max:36'],
            'due_dates.*' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

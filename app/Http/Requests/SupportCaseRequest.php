<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['refund', 'cancellation', 'question', 'shirt_change', 'other'])],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'order_code' => ['nullable', 'string', 'exists:orders,code'],
            'ticket_code' => ['nullable', 'string', 'exists:tickets,code'],
        ];
    }
}

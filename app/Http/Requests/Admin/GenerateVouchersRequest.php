<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateVouchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'ticket_type_id' => ['nullable',
                Rule::exists('ticket_types', 'id')
                    ->where('event_id', $this->route('event')?->id)],
        ];
    }
}

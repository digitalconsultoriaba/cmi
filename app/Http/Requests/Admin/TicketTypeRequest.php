<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'seats_per_ticket' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'is_couple' => ['sometimes', 'boolean'],
            'includes_shirt' => ['sometimes', 'boolean'],
            'includes_kit' => ['sometimes', 'boolean'],
            'is_courtesy' => ['sometimes', 'boolean'],
            'audience' => ['sometimes', Rule::in(['any', 'adult', 'child', 'guest'])],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

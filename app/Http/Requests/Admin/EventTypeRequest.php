<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('event_types', 'name')->ignore($this->route('event_type'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

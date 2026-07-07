<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ParticipantCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/i'],
            'label' => ['required', 'string', 'max:120'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'A chave deve conter apenas letras, números e underscore.',
            'label.required' => 'Informe o rótulo da categoria.',
        ];
    }
}

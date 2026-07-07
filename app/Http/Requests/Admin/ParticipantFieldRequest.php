<?php

namespace App\Http\Requests\Admin;

use App\Domain\Events\Models\ParticipantField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParticipantFieldRequest extends FormRequest
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
            'type' => ['required', Rule::in(ParticipantField::TYPES)],
            'required' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'config' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipo de campo inválido.',
            'label.required' => 'Informe o rótulo do campo.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // papel garantido pelo middleware require.role:admin
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'event_type_id' => ['required', 'exists:event_types,id'],
            'starts_at' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'total_capacity' => ['nullable', 'integer', 'min:1'],
            'pricing_mode' => ['nullable', 'in:paid,free,mixed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do evento.',
            'event_type_id.required' => 'Escolha o tipo de evento.',
            'starts_at.required' => 'Informe a data e hora do evento.',
        ];
    }
}

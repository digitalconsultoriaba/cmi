<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // papel garantido por require.role:treasury na rota
    }

    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:10', 'max:500'],
            'method' => ['nullable', 'string', 'max:30'],
            'paid_at' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'justification.required' => 'A justificativa da baixa manual é obrigatória.',
            'justification.min' => 'Descreva a justificativa com mais detalhes.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // papel garantido por require.role:treasury na rota
    }

    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:10', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'justification.required' => 'A justificativa do estorno é obrigatória.',
            'justification.min' => 'Descreva a justificativa com mais detalhes.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CardCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Anti-PAN na borda: token que pareça número de cartão é recusado
            // ANTES de tocar qualquer código (422 não gera stack trace em log).
            'token' => ['required', 'string', 'max:100', 'not_regex:/\d{13,}/'],
            'installments' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.not_regex' => 'Token de pagamento inválido.',
            'installments.min' => 'Parcelas devem ser entre 1 e 12.',
            'installments.max' => 'Parcelas devem ser entre 1 e 12.',
        ];
    }
}

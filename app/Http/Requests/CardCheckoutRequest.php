<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CardCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Normaliza as máscaras vindas do form: CPF/CNPJ, telefone e CEP só dígitos. */
    protected function prepareForValidation(): void
    {
        $customer = $this->input('customerData');
        if (! is_array($customer)) {
            return;
        }

        foreach (['cpfCnpj', 'phoneNumber', 'postalCode'] as $key) {
            if (isset($customer[$key])) {
                $customer[$key] = preg_replace('/\D/', '', (string) $customer[$key]);
            }
        }

        $this->merge(['customerData' => $customer]);
    }

    public function rules(): array
    {
        return [
            // Anti-PAN na borda: token que pareça número de cartão é recusado
            // ANTES de tocar qualquer código (422 não gera stack trace em log).
            // Opcional: o checkout hospedado (ASAAS) não usa token — só parcelas.
            'token' => ['nullable', 'string', 'max:100', 'not_regex:/\d{13,}/'],
            'installments' => ['required', 'integer', 'min:1', 'max:12'],

            // Pré-preenchimento do comprador na página hospedada (opcional; o
            // provedor exige o conjunto completo, então todos required_with).
            // Valores já normalizados (só dígitos) por prepareForValidation.
            'customerData' => ['nullable', 'array'],
            'customerData.name' => ['required_with:customerData', 'string', 'max:100'],
            'customerData.email' => ['nullable', 'email', 'max:120'],
            'customerData.cpfCnpj' => ['required_with:customerData', 'string', 'regex:/^(\d{11}|\d{14})$/'],
            'customerData.phoneNumber' => ['required_with:customerData', 'string', 'regex:/^\d{10,13}$/'],
            'customerData.postalCode' => ['required_with:customerData', 'string', 'regex:/^\d{8}$/'],
            'customerData.address' => ['required_with:customerData', 'string', 'max:150'],
            'customerData.addressNumber' => ['required_with:customerData', 'string', 'max:20'],
            'customerData.complement' => ['nullable', 'string', 'max:100'],
            'customerData.province' => ['required_with:customerData', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.not_regex' => 'Token de pagamento inválido.',
            'installments.min' => 'Parcelas devem ser entre 1 e 12.',
            'installments.max' => 'Parcelas devem ser entre 1 e 12.',
            'customerData.cpfCnpj.regex' => 'CPF/CNPJ inválido.',
            'customerData.phoneNumber.regex' => 'Telefone inválido.',
            'customerData.postalCode.regex' => 'CEP inválido.',
        ];
    }
}

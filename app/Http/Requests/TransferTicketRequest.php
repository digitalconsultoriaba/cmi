<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->participant_email)) {
            $this->merge(['participant_email' => mb_strtolower(trim($this->participant_email))]);
        }
    }

    public function rules(): array
    {
        return [
            'participant_name' => ['required', 'string', 'max:255'],
            'participant_email' => ['required', 'email', 'max:255'],
            'participant_document' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'participant_name.required' => 'Informe o nome do novo participante.',
            'participant_email.required' => 'Informe o e-mail do novo participante.',
        ];
    }
}

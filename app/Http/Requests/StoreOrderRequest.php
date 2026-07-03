<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sessão garantida pelo middleware auth:sanctum
    }

    public function rules(): array
    {
        $max = (int) config('events.max_tickets_per_order');

        return [
            'event_slug' => ['required', 'string', 'exists:events,slug'],
            'items' => ['array', "max:$max", 'required_without:voucher_code'],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.participant_name' => ['required', 'string', 'max:255'],
            'items.*.participant_email' => ['nullable', 'email', 'max:255'],
            'items.*.participant_document' => ['nullable', 'string', 'max:30'],
            'items.*.shirt_model_id' => ['nullable', 'integer'],
            'items.*.shirt_size_id' => ['nullable', 'integer'],
            'items.*.companion_name' => ['nullable', 'string', 'max:255'],
            'items.*.companion_shirt_model_id' => ['nullable', 'integer'],
            'items.*.companion_shirt_size_id' => ['nullable', 'integer'],
            'courtesy_participants' => ['array', "max:$max"],
            'courtesy_participants.*.participant_name' => ['required', 'string', 'max:255'],
            'courtesy_participants.*.participant_email' => ['nullable', 'email'],
            'voucher_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required_without' => 'O carrinho está vazio.',
            'items.max' => 'Máximo de :max ingressos por pedido.',
            'items.*.participant_name.required' => 'Informe o nome do participante.',
        ];
    }
}

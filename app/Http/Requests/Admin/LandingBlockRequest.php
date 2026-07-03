<?php

namespace App\Http\Requests\Admin;

use App\Domain\Events\Models\LandingBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LandingBlockRequest extends FormRequest
{
    /** Regras de payload por tipo de bloco (research, Decisão 5). */
    private const PAYLOAD_RULES = [
        'hero' => ['payload.title' => ['required', 'string', 'max:255']],
        'text' => ['payload.body' => ['required', 'string']],
        'schedule' => ['payload.items' => ['required', 'array', 'min:1']],
        'speakers' => ['payload.items' => ['required', 'array', 'min:1']],
        'faq' => ['payload.items' => ['required', 'array', 'min:1']],
        'location' => ['payload.address' => ['required', 'string', 'max:255']],
        'cta' => ['payload.label' => ['required', 'string', 'max:100']],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');

        return array_merge([
            'type' => ['required', Rule::in(LandingBlock::TYPES)],
            'payload' => ['required', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ], self::PAYLOAD_RULES[$type] ?? []);
    }

    public function messages(): array
    {
        return [
            'payload.title.required' => 'A capa precisa de um título.',
            'payload.body.required' => 'O bloco de texto precisa de conteúdo.',
            'payload.items.required' => 'Inclua ao menos um item.',
            'payload.address.required' => 'Informe o endereço do local.',
            'payload.label.required' => 'Informe o texto do botão.',
        ];
    }
}

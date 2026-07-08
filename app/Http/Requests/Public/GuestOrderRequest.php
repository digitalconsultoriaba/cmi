<?php

namespace App\Http\Requests\Public;

use App\Domain\Events\Models\Event;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class GuestOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checkout público (guest)
    }

    public function rules(): array
    {
        $max = (int) config('events.max_tickets_per_order');

        return [
            'event_slug' => ['required', 'string', 'exists:events,slug'],
            'buyer' => ['required', 'array'],
            'buyer.name' => ['required', 'string', 'max:255'],
            'buyer.email' => ['required', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1', "max:$max"],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.participant_name' => ['required', 'string', 'max:255'],
            'items.*.participant_email' => ['nullable', 'email', 'max:255'],
            'items.*.participant_document' => ['nullable', 'string', 'max:30'],
            'items.*.whatsapp' => ['nullable', 'string', 'max:30'],
            'items.*.category_key' => ['nullable', 'string', 'max:40'],
            'items.*.fields' => ['nullable', 'array'],
            'items.*.voucher_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'buyer.name.required' => 'Informe o nome do comprador.',
            'buyer.email.required' => 'Informe o e-mail do comprador.',
            'items.required' => 'Adicione ao menos um participante.',
            'items.max' => 'Máximo de :max inscrições por pedido.',
            'items.*.participant_name.required' => 'Informe o nome do participante.',
        ];
    }

    /** Validações que dependem da config do evento (categoria/campos/e-mail). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $items = $this->input('items', []);
            $multi = count($items) > 1;

            $event = Event::query()->where('slug', $this->input('event_slug'))->first();
            $categories = $event
                ? $event->participantCategories()->where('is_active', true)->with('fields')->get()->keyBy('key')
                : collect();

            foreach ($items as $i => $item) {
                // E-mail do participante é obrigatório quando há mais de um.
                if ($multi && blank($item['participant_email'] ?? null)) {
                    $v->errors()->add("items.$i.participant_email", 'Informe o e-mail deste participante.');
                }

                $categoryKey = $item['category_key'] ?? null;
                if ($categoryKey === null) {
                    continue; // evento sem categorias configuradas
                }

                $category = $categories->get($categoryKey);
                if ($category === null) {
                    $v->errors()->add("items.$i.category_key", 'Categoria de participante inválida.');

                    continue;
                }

                $fields = $item['fields'] ?? [];
                foreach ($category->fields as $field) {
                    $value = $fields[$field->key] ?? null;
                    if ($field->required && blank($value)) {
                        $v->errors()->add("items.$i.fields.{$field->key}", "Preencha o campo \"{$field->label}\".");
                    }
                }
            }
        });
    }
}

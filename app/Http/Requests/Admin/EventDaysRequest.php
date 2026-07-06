<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EventDaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // papel garantido pelo middleware require.role:admin,treasury
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1', 'max:3'],
            'days.*.date' => ['required', 'date', 'distinct'],
            'days.*.startsAt' => ['nullable', 'date_format:H:i'],
            'days.*.endsAt' => ['nullable', 'date_format:H:i'],
            'days.*.label' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.max' => 'O evento pode ter no máximo 3 dias.',
            'days.*.date.distinct' => 'As datas dos dias devem ser distintas.',
            'days.*.date.required' => 'Informe a data de cada dia.',
        ];
    }

    /** Normaliza HH:MM → HH:MM:00 para a coluna time. */
    public function days(): array
    {
        return collect($this->validated('days'))->map(fn ($d) => [
            'date' => $d['date'],
            'startsAt' => ! empty($d['startsAt']) ? $d['startsAt'].':00' : null,
            'endsAt' => ! empty($d['endsAt']) ? $d['endsAt'].':00' : null,
            'label' => $d['label'] ?? null,
        ])->all();
    }
}

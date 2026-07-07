<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->route('event')?->eventSite?->id;
        $supported = (array) config('site.locales', ['pt', 'en', 'es']);

        return [
            'slug' => ['sometimes', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/i',
                Rule::unique('event_sites', 'slug')->ignore($siteId)->whereNull('deleted_at')],
            'theme' => ['sometimes', 'array'],
            'identity' => ['sometimes', 'array'],
            'countdownAt' => ['sometimes', 'nullable', 'date'],
            'seo' => ['sometimes', 'array'],
            'activeLanguages' => ['sometimes', 'array'],
            'activeLanguages.*' => [Rule::in($supported)],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'O endereço (slug) deve conter apenas letras, números e hífens.',
            'slug.unique' => 'Este endereço (slug) já está em uso por outro site.',
            'countdownAt.date' => 'Informe uma data válida para o countdown.',
            'activeLanguages.*.in' => 'Idioma não suportado.',
        ];
    }
}

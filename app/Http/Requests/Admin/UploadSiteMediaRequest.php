<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadSiteMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,svg', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione uma imagem.',
            'file.mimes' => 'Formatos aceitos: JPG, PNG, WEBP ou SVG.',
            'file.max' => 'A imagem deve ter no máximo 4 MB.',
        ];
    }
}

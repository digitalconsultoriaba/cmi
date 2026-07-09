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
            // SVG fora: pode conter <script> e é servido inline no mesmo domínio
            // (XSS armazenado). Só formatos raster.
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione uma imagem.',
            'file.mimes' => 'Formatos aceitos: JPG, PNG ou WEBP.',
            'file.max' => 'A imagem deve ter no máximo 4 MB.',
        ];
    }
}

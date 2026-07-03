<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'banner' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'banner.mimes' => 'O banner deve ser uma imagem JPEG, PNG ou WebP.',
            'banner.max' => 'O banner deve ter no máximo 5 MB.',
        ];
    }
}

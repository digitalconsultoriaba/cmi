<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payload' => ['sometimes', 'array'],
            'parentItemId' => ['sometimes', 'nullable', 'integer'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

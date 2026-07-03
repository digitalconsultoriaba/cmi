<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // papel garantido pelo middleware require.role:admin
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('events', 'slug')->ignore($this->route('event'))],
            'description' => ['nullable', 'string'],
            'event_type_id' => ['sometimes', 'required', 'exists:event_types,id'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_map_url' => ['nullable', 'url', 'max:255'],
            'total_capacity' => ['nullable', 'integer', 'min:1'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'reservation_ttl_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'participation_rules' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'pricing_mode' => ['sometimes', Rule::in(['paid', 'free', 'mixed'])],
            'allow_card' => ['sometimes', 'boolean'],
            'allow_boleto' => ['sometimes', 'boolean'],
            'allow_pix' => ['sometimes', 'boolean'],
            'allow_shirt_choice' => ['sometimes', 'boolean'],
            'requires_shirt' => ['sometimes', 'boolean'],
            'allow_kit' => ['sometimes', 'boolean'],
            'allow_transfer' => ['sometimes', 'boolean'],
            'allow_user_cancel' => ['sometimes', 'boolean'],
            'allow_refund_request' => ['sometimes', 'boolean'],
            'allow_courtesy' => ['sometimes', 'boolean'],
            'courtesy_paid_threshold' => ['nullable', 'integer', 'min:1'],
            'courtesy_grant_per_threshold' => ['sometimes', 'integer', 'min:1'],
            'courtesy_limit_per_account' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

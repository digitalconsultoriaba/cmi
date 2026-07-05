<?php

namespace App\Http\Requests\Admin;

use App\Domain\Events\Models\BudgetSponsorshipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetSponsorshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'unitValue' => [$creating ? 'required' : 'sometimes', 'numeric', 'gt:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(BudgetSponsorshipStatus::ALL)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function columns(): array
    {
        $map = [
            'name' => 'name', 'unitValue' => 'unit_value',
            'quantity' => 'quantity', 'status' => 'status', 'notes' => 'notes',
        ];
        $out = [];
        foreach ($this->validated() as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }

        return $out;
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BudgetScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paying' => ['required', 'integer', 'min:0'],
            'avgTicket' => ['required', 'numeric', 'min:0'],
            'sponsorship' => ['required', 'numeric', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'otherRevenue' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function columns(): array
    {
        $map = [
            'paying' => 'paying', 'avgTicket' => 'avg_ticket',
            'sponsorship' => 'sponsorship', 'cost' => 'cost', 'otherRevenue' => 'other_revenue',
        ];
        $out = [];
        foreach ($this->validated() as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }

        return $out;
    }
}

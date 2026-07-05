<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // papel garantido pelo middleware require.role:admin,treasury
    }

    public function rules(): array
    {
        return [
            'expectedPaying' => ['sometimes', 'integer', 'min:0'],
            'expectedCourtesy' => ['sometimes', 'integer', 'min:0'],
            'expectedGuests' => ['sometimes', 'integer', 'min:0'],
            'expectedStaff' => ['sometimes', 'integer', 'min:0'],
            'expectedSpeakers' => ['sometimes', 'integer', 'min:0'],
            'otherRevenue' => ['sometimes', 'numeric', 'min:0'],
            'safetyMarginPct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Mapeia camelCase → colunas snake_case. */
    public function columns(): array
    {
        $map = [
            'expectedPaying' => 'expected_paying',
            'expectedCourtesy' => 'expected_courtesy',
            'expectedGuests' => 'expected_guests',
            'expectedStaff' => 'expected_staff',
            'expectedSpeakers' => 'expected_speakers',
            'otherRevenue' => 'other_revenue',
            'safetyMarginPct' => 'safety_margin_pct',
            'notes' => 'notes',
        ];

        $out = [];
        foreach ($this->validated() as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }

        return $out;
    }
}

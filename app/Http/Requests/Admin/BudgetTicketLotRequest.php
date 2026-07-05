<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BudgetTicketLotRequest extends FormRequest
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
            'unitPrice' => [$creating ? 'required' : 'sometimes', 'numeric', 'gt:0'],
            'expectedQuantity' => [$creating ? 'required' : 'sometimes', 'integer', 'min:0'],
            'expectedPaying' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function columns(): array
    {
        $map = [
            'name' => 'name', 'unitPrice' => 'unit_price',
            'expectedQuantity' => 'expected_quantity', 'expectedPaying' => 'expected_paying',
            'notes' => 'notes',
        ];
        $out = [];
        foreach ($this->validated() as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }

        return $out;
    }
}

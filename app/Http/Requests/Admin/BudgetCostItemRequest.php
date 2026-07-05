<?php

namespace App\Http\Requests\Admin;

use App\Domain\Events\Models\BudgetCategory;
use App\Domain\Events\Models\BudgetCostItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetCostItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'description' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'category' => [$creating ? 'required' : 'sometimes', Rule::in(BudgetCategory::ALL)],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unitPrice' => ['nullable', 'numeric', 'gt:0'],
            'totalAmount' => ['nullable', 'numeric', 'gt:0'],
            'supplierName' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(BudgetCostItemStatus::ALL)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Precisa de (quantidade+unitário) OU total informado.
            $hasComputed = $this->filled('quantity') && $this->filled('unitPrice');
            if (! $hasComputed && ! $this->filled('totalAmount') && $this->isMethod('post')) {
                $validator->errors()->add('totalAmount', 'Informe o valor total ou quantidade e valor unitário.');
            }
        });
    }

    public function columns(): array
    {
        $map = [
            'description' => 'description', 'category' => 'category',
            'quantity' => 'quantity', 'unitPrice' => 'unit_price',
            'totalAmount' => 'total_amount', 'supplierName' => 'supplier_name',
            'status' => 'status', 'notes' => 'notes',
        ];
        $out = [];
        foreach ($this->validated() as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }

        return $out;
    }
}

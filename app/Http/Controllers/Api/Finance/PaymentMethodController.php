<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Models\FinancialPaymentMethod;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return ApiResponse::data(FinancialPaymentMethod::query()->orderBy('sort')->orderBy('name')->get()
            ->map(fn ($m) => ['id' => $m->id, 'slug' => $m->slug, 'name' => $m->name, 'isActive' => (bool) $m->is_active])->all());
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $m = FinancialPaymentMethod::query()->create([
            'name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::random(4), 'is_active' => true,
        ]);

        return ApiResponse::data(['id' => $m->id, 'name' => $m->name], 201);
    }

    public function update(Request $request, FinancialPaymentMethod $paymentMethod)
    {
        $paymentMethod->update($request->validate([
            'name' => ['sometimes', 'string', 'max:60'], 'is_active' => ['sometimes', 'boolean'],
        ]));

        return ApiResponse::data(['id' => $paymentMethod->id, 'name' => $paymentMethod->name, 'isActive' => (bool) $paymentMethod->is_active]);
    }
}

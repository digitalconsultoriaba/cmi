<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Models\FinancialEntry;
use App\Domain\Events\Services\FinancialEntryService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function __construct(private readonly FinancialEntryService $service) {}

    public function settle(Request $request, FinancialEntry $entry)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'settled_on' => ['required', 'date'],
            'payment_method_id' => ['nullable', 'integer', 'exists:financial_payment_methods,id'],
            'bank_account' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string'],
        ]);
        $entry = $this->service->settle($entry, $data, $request->user());

        return ApiResponse::data([
            'status' => $entry->status(), 'statusLabel' => $entry->statusLabel(),
            'settledAmount' => number_format((float) $entry->settled_amount, 2, '.', ''),
            'balance' => $entry->balance(),
        ]);
    }

    public function reverse(Request $request, FinancialEntry $entry)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'settled_on' => ['nullable', 'date'],
        ]);
        $entry = $this->service->reverse($entry, $data, $request->user());

        return ApiResponse::data([
            'status' => $entry->status(),
            'settledAmount' => number_format((float) $entry->settled_amount, 2, '.', ''),
            'balance' => $entry->balance(),
        ]);
    }
}

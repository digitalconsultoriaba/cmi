<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\FinancialReportService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly FinancialReportService $reports) {}

    public function show(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'event' => ['nullable', 'integer'], 'direction' => ['nullable', 'in:payable,receivable'],
            'category' => ['nullable', 'integer'], 'paymentMethod' => ['nullable', 'integer'],
        ]);

        return ApiResponse::data($this->reports->dashboard($filters));
    }

    public function eventResult(Event $event)
    {
        return ApiResponse::data($this->reports->eventResult($event));
    }
}

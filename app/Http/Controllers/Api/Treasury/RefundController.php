<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Services\RefundPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExecuteRefundRequest;
use App\Http\Resources\SupportCaseResource;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    /** Fila de devoluções: casos refund abertos/reabertos. */
    public function index(Request $request)
    {
        $cases = SupportCase::query()
            ->where('type', 'refund')
            ->whereIn('status', ['open', 'reopened'])
            ->with(['order.payments.status', 'ticket', 'user'])
            ->oldest('created_at')
            ->get();

        return response()->json([
            'data' => $cases
                ->map(fn (SupportCase $case) => SupportCaseResource::forStaff($case)->toArray($request))
                ->values(),
        ]);
    }

    public function execute(ExecuteRefundRequest $request, SupportCase $supportCase, RefundPayment $refund)
    {
        $case = $refund->execute(
            $supportCase,
            $request->user(),
            $request->validated('justification'),
            $request->validated('amount'),
        );

        return response()->json([
            'data' => SupportCaseResource::forStaff($case->load(['notes.author', 'order', 'ticket']))
                ->toArray($request),
        ]);
    }
}

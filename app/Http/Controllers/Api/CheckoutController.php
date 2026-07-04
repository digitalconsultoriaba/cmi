<?php

namespace App\Http\Controllers\Api;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Services\CreateCharge;
use App\Http\Controllers\Controller;
use App\Http\Requests\CardCheckoutRequest;
use App\Http\Resources\PaymentResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private readonly CreateCharge $createCharge)
    {
    }

    public function pix(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        return PaymentResource::make($this->createCharge->pix($order))
            ->response()->setStatusCode(201);
    }

    public function boleto(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        return PaymentResource::make($this->createCharge->boleto($order))
            ->response()->setStatusCode(201);
    }

    public function card(CardCheckoutRequest $request, Order $order)
    {
        $this->authorize('view', $order);

        $payment = $this->createCharge->card(
            $order,
            $request->validated('token'),
            (int) $request->validated('installments'),
        );

        return PaymentResource::make($payment->fresh());
    }

    public function paymentStatus(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $lastPaid = $order->payments()->latest('paid_at')->whereNotNull('paid_at')->first();

        return ApiResponse::data([
            'status' => $order->status?->slug,
            'paidAt' => $lastPaid?->paid_at?->toISOString(),
        ]);
    }
}

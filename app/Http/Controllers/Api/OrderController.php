<?php

namespace App\Http\Controllers\Api;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Services\OrderReceiptPdf;
use App\Domain\Events\Services\TicketPurchaseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly TicketPurchaseService $service)
    {
    }

    public function store(StoreOrderRequest $request)
    {
        $event = Event::query()->where('slug', $request->validated('event_slug'))->firstOrFail();

        $orders = $this->service->purchase(
            $event,
            $request->user(),
            $request->validated('items') ?? [],
            $request->validated('courtesy_participants') ?? [],
            $request->validated('voucher_code'),
        );

        return response()->json([
            'data' => ['orders' => OrderResource::collection(collect($orders)->map->load('tickets.status', 'event'))],
        ], 201);
    }

    public function index(Request $request)
    {
        $orders = Order::query()
            ->where('buyer_user_id', $request->user()->id)
            ->with(['event', 'status', 'tickets.status', 'tickets.ticketType', 'payments.status'])
            ->latest('id')
            ->get();

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        return OrderResource::make($order->load(['event', 'status', 'tickets.status', 'tickets.ticketType', 'payments.status']));
    }

    /** Comprovante de compra em PDF — só após confirmação do pagamento. */
    public function receipt(Request $request, Order $order, OrderReceiptPdf $pdf)
    {
        $this->authorize('view', $order);

        if ($order->status?->slug !== OrderStatus::PAID) {
            throw new DomainRuleViolation(
                'O comprovante fica disponível após a confirmação do pagamento.',
                'not_confirmed'
            );
        }

        return $pdf->download($order);
    }
}

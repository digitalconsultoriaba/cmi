<?php

namespace App\Http\Controllers\Api;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Services\TicketLifecycleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransferTicketRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TicketResource;
use Illuminate\Http\Request;

class TicketLifecycleController extends Controller
{
    public function __construct(private readonly TicketLifecycleService $lifecycle)
    {
    }

    public function cancelTicket(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'confirm_no_refund' => ['nullable', 'boolean'],
        ]);

        $cancelled = $this->lifecycle->cancelTicket(
            $ticket,
            $request->user(),
            $data['reason'] ?? null,
            (bool) ($data['confirm_no_refund'] ?? false),
        );

        return TicketResource::make($cancelled->load(['event', 'status', 'ticketType', 'order']));
    }

    public function cancelOrder(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'confirm_no_refund' => ['nullable', 'boolean'],
        ]);

        $cancelled = $this->lifecycle->cancelOrder(
            $order,
            $request->user(),
            $data['reason'] ?? null,
            (bool) ($data['confirm_no_refund'] ?? false),
        );

        return OrderResource::make($cancelled->load(['event', 'status', 'tickets.status', 'tickets.ticketType']));
    }

    public function transfer(TransferTicketRequest $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $newTicket = $this->lifecycle->transferTicket($ticket, $request->user(), $request->validated());

        return TicketResource::make($newTicket->load(['event', 'status', 'ticketType', 'order']))
            ->response()->setStatusCode(201);
    }
}

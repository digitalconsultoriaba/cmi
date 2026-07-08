<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Services\TicketLifecycleService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Ficha do CLIENTE (spec 009) — o comprador visto como cliente de loja
 * virtual: dados, compras, ingressos, histórico e mensagens (reusa o
 * Atendimento da 006). Cancelamento segue a política de reembolso (006).
 */
class CustomerController extends Controller
{
    /** Dados do cliente + compras + ingressos + histórico (escopo do evento). */
    public function show(Event $event, User $user)
    {
        $orders = $event->orders()
            ->where('buyer_user_id', $user->id)
            ->with(['status', 'payments.status', 'tickets.ticketType', 'tickets.status',
                'tickets.shirtModel', 'tickets.shirtSize'])
            ->orderByDesc('id')->get();

        $tickets = $orders->flatMap->tickets;
        $codes = $orders->pluck('code')->merge($tickets->pluck('code'))->filter()->all();

        // Cortesias: quem distribuiu o voucher (e quando) por ingresso resgatado
        $courtesyByTicket = \App\Domain\Events\Models\CourtesyVoucher::query()
            ->whereIn('redeemed_ticket_id', $tickets->pluck('id')->filter())
            ->with('distributedBy')
            ->get()->keyBy('redeemed_ticket_id');

        // Trilha (006/008) por código do pedido/ingresso + notas manuais do cliente
        $history = Activity::query()->with('causer')->latest('id')->get()
            ->filter(fn ($a) => in_array($a->properties['reference'] ?? null, $codes, true)
                || ($a->log_name === 'customer.note'
                    && (int) $a->subject_id === $user->id
                    && $a->subject_type === User::class))
            ->take(80)
            ->map(fn ($a) => [
                'action' => $a->log_name,
                'description' => $a->description,
                'causer' => $a->causer?->name,
                'createdAt' => $a->created_at?->toISOString(),
            ])->values()->all();

        return ApiResponse::data([
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'document' => $user->document,
            ],
            'stats' => [
                'ordersCount' => $orders->count(),
                'ticketsCount' => $tickets->count(),
                'totalPaid' => number_format(
                    (float) $orders->flatMap->payments
                        ->filter(fn ($p) => $p->status?->slug === PaymentStatus::PAID)
                        ->sum(fn ($p) => (float) $p->amount),
                    2, '.', ''
                ),
            ],
            'orders' => $orders->map(function (Order $o) {
                $paid = $o->payments->firstWhere('status.slug', PaymentStatus::PAID);

                return [
                    'code' => $o->code,
                    'createdAt' => $o->created_at?->toISOString(),
                    'status' => $o->status?->slug,
                    'statusLabel' => $o->status?->name,
                    'total' => number_format((float) $o->total_amount, 2, '.', ''),
                    'method' => $paid?->method,
                    'paidAt' => $paid?->paid_at?->toISOString(),
                    'canCancel' => ! in_array($o->status?->slug,
                        \App\Domain\Events\Models\OrderStatus::TERMINAL, true),
                ];
            })->values()->all(),
            'tickets' => $tickets->map(function (Ticket $t) use ($courtesyByTicket) {
                $voucher = $courtesyByTicket->get($t->id);

                return [
                    'code' => $t->code,
                    'orderCode' => $t->order?->code,
                    'participantName' => $t->participant_name,
                    'companionName' => $t->companion_name,
                    'ticketTypeName' => $t->ticketType?->name,
                    'unitPrice' => number_format((float) $t->unit_price, 2, '.', ''),
                    // Pedido coletivo (bloco): mais de um ingresso no mesmo pedido.
                    'orderTicketsCount' => $t->order?->tickets->count() ?? 1,
                    'orderTotal' => $t->order ? number_format((float) $t->order->total_amount, 2, '.', '') : null,
                    'shirt' => $t->shirtSize?->label
                        ? $t->shirtSize->label.($t->shirtModel?->label ? '/'.$t->shirtModel->label : '')
                        : null,
                    'status' => $t->status?->slug,
                    'statusLabel' => $t->status?->name,
                    'usedAt' => $t->used_at?->toISOString(),
                    'validatedBy' => $t->validated_by
                        ? \App\Models\User::query()->find($t->validated_by)?->name : null,
                    // Cortesia: quem deu (voucher) ou regra automática do evento
                    'isCourtesy' => (bool) $t->is_courtesy,
                    'courtesyGivenBy' => $t->is_courtesy
                        ? ($voucher?->distributedBy?->name ?? 'Automática (regra do evento)')
                        : null,
                    'courtesyCode' => $voucher?->code,
                    'printable' => in_array($t->status?->slug, [
                        TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
                    ], true),
                    'canCancel' => in_array($t->status?->slug, TicketStatus::LIVE, true),
                ];
            })->values()->all(),
            'history' => $history,
        ]);
    }

    /** Adiciona uma nota manual ao histórico do cliente (spec 009). */
    public function addHistory(Request $request, Event $event, User $user)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        activity('customer.note')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['reference' => null])
            ->log($data['note']);

        return $this->show($event, $user);
    }

    /** Thread de mensagens (reusa o caso de atendimento da 006). */
    public function messages(Event $event, User $user)
    {
        $case = $this->messageCase($event, $user, create: false);

        return ApiResponse::data([
            'caseId' => $case?->id,
            'messages' => $case
                ? $case->notes()->with('author')->orderBy('id')->get()
                    ->filter(fn ($n) => $n->visible_to_attendee)
                    ->map(fn ($n) => [
                        'body' => $n->body,
                        'fromAttendee' => (bool) $n->from_attendee,
                        'author' => $n->author?->name,
                        'createdAt' => $n->created_at?->toISOString(),
                    ])->values()->all()
                : [],
        ]);
    }

    /** Envia uma mensagem ao cliente (nota visível no atendimento). */
    public function sendMessage(Request $request, Event $event, User $user)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $case = $this->messageCase($event, $user, create: true);
        $case->notes()->create([
            'author_user_id' => $request->user()->id,
            'body' => $data['message'],
            'visible_to_attendee' => true,
            'from_attendee' => false,
        ]);

        if (in_array($case->status, ['finished'], true)) {
            $case->forceFill(['status' => 'reopened'])->save();
        }

        return $this->messages($event, $user);
    }

    /** Cancelamento pelo staff — segue a política de reembolso (006). */
    public function cancelTicket(Request $request, Ticket $ticket, TicketLifecycleService $lifecycle)
    {
        $reason = $request->input('reason', 'Cancelado pela organização');
        $ticket = $lifecycle->cancelTicket($ticket, $request->user(), $reason,
            confirmNoRefund: true, byStaff: true);

        return ApiResponse::data(['code' => $ticket->code, 'status' => $ticket->status?->slug]);
    }

    public function cancelOrder(Request $request, Order $order, TicketLifecycleService $lifecycle)
    {
        $reason = $request->input('reason', 'Cancelado pela organização');
        $order = $lifecycle->cancelOrder($order, $request->user(), $reason,
            confirmNoRefund: true, byStaff: true);

        return ApiResponse::data(['code' => $order->code, 'status' => $order->status?->slug]);
    }

    /** Caso de atendimento tipo "message" único por (evento, cliente). */
    private function messageCase(Event $event, User $user, bool $create): ?SupportCase
    {
        $case = SupportCase::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('type', 'message')
            ->latest('id')
            ->first();

        if ($case === null && $create) {
            $case = SupportCase::query()->create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'type' => 'message',
                'status' => 'open',
                'subject' => 'Conversa com a organização',
            ]);
        }

        return $case;
    }
}

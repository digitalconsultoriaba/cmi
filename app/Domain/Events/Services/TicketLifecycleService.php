<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Models\User;
use App\Notifications\TicketCancelledPtBr;
use App\Notifications\TicketTransferredPtBr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Cancelamento e transferência sob o mesmo lock da compra (princípio II) e
 * com histórico integral (princípio V).
 */
class TicketLifecycleService
{
    public function __construct(
        private readonly RefundPolicy $policy,
        private readonly CreateCharge $createCharge,
    ) {
    }

    public function cancelTicket(Ticket $ticket, User $actor, ?string $reason, bool $confirmNoRefund = false, bool $byStaff = false): Ticket
    {
        [$ticket, $case] = DB::transaction(function () use ($ticket, $actor, $reason, $confirmNoRefund, $byStaff) {
            Event::query()->whereKey($ticket->event_id)->lockForUpdate()->first();
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();

            $this->ensureCancellable($ticket->event, $ticket, $byStaff);

            $isPaid = $this->isPaidTicket($ticket);
            $refund = $isPaid ? $this->policy->refundableAmount($ticket) : null;

            if ($isPaid && bccomp($refund, '0.00', 2) === 0 && ! $confirmNoRefund) {
                throw new DomainRuleViolation(
                    'Pela política do evento, este cancelamento não tem devolução. Confirme para prosseguir.',
                    'refund_confirmation_required'
                );
            }

            $ticket->forceFill([
                'cancel_requested_by' => $actor->id,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason ?: 'Cancelado pelo inscrito',
            ]);
            $ticket->transitionTo(TicketStatus::CANCELLED);

            $this->recountAround($ticket);

            activity('ticket.cancelled')
                ->performedOn($ticket)
                ->causedBy($actor)
                ->withProperties(['reference' => $ticket->code, 'reason' => $ticket->cancel_reason])
                ->log('Ingresso '.$ticket->code.' cancelado');

            $case = null;
            if ($isPaid && bccomp($refund, '0.00', 2) === 1) {
                $case = $this->openRefundCase($ticket->order, $ticket, $refund, $actor,
                    'Cancelamento de ingresso pelo inscrito — política: devolução integral.');
            }

            return [$ticket->fresh(), $case];
        });

        $this->notifyQuietly($ticket->order, new TicketCancelledPtBr($ticket, $case?->refund_amount));

        return $ticket;
    }

    public function cancelOrder(Order $order, User $actor, ?string $reason, bool $confirmNoRefund = false, bool $byStaff = false): Order
    {
        $order = DB::transaction(function () use ($order, $actor, $reason, $confirmNoRefund, $byStaff) {
            Event::query()->whereKey($order->event_id)->lockForUpdate()->first();
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (in_array($order->status?->slug, OrderStatus::TERMINAL, true)) {
                throw new DomainRuleViolation('Este pedido não pode mais ser cancelado.', 'terminal_status');
            }

            $this->ensureCancellable($order->event, null, $byStaff);

            $paidAmount = $order->amountPaid();
            $hasPayment = bccomp($paidAmount, '0.00', 2) === 1;
            $refund = $hasPayment ? $this->policy->refundableForOrder($order) : null;

            if ($hasPayment && bccomp($refund, '0.00', 2) === 0 && ! $confirmNoRefund) {
                throw new DomainRuleViolation(
                    'Pela política do evento, este cancelamento não tem devolução. Confirme para prosseguir.',
                    'refund_confirmation_required'
                );
            }

            $liveIds = TicketStatus::idsFor(TicketStatus::LIVE);
            $order->tickets()->whereIn('status_id', $liveIds)->get()->each(function (Ticket $ticket) use ($actor, $reason) {
                $ticket->forceFill([
                    'cancel_requested_by' => $actor->id,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->id,
                    'cancel_reason' => $reason ?: 'Pedido cancelado pelo inscrito',
                ]);
                $ticket->transitionTo(TicketStatus::CANCELLED);
                $this->recountAround($ticket);
            });

            $this->createCharge->expirePendingPayments($order);
            $order->transitionTo(OrderStatus::CANCELLED);

            activity('order.cancelled')
                ->performedOn($order)
                ->causedBy($actor)
                ->withProperties(['reference' => $order->code])
                ->log('Pedido '.$order->code.' cancelado');

            if ($hasPayment && bccomp($refund, '0.00', 2) === 1) {
                $this->openRefundCase($order, null, $refund, $actor,
                    'Cancelamento do pedido pelo inscrito — política: devolução integral.');
            }

            return $order->fresh();
        });

        $this->notifyQuietly($order, new TicketCancelledPtBr(null, null, $order));

        return $order;
    }

    public function transferTicket(Ticket $ticket, User $actor, array $participant): Ticket
    {
        $newTicket = DB::transaction(function () use ($ticket, $actor, $participant) {
            Event::query()->whereKey($ticket->event_id)->lockForUpdate()->first();
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();
            $event = $ticket->event;

            if (! $event->allow_transfer) {
                throw new DomainRuleViolation('Este evento não permite transferência de ingressos.', 'not_transferable');
            }

            if (! in_array($ticket->status?->slug, [TicketStatus::PAID, TicketStatus::CONFIRMED], true)) {
                throw new DomainRuleViolation(
                    'Apenas ingressos pagos/confirmados podem ser transferidos.',
                    'not_transferable'
                );
            }

            if ($event->starts_at !== null && Carbon::now()->gte($event->starts_at)) {
                throw new DomainRuleViolation('O evento já começou — transferência indisponível.', 'not_transferable');
            }

            if (CourtesyVoucher::query()->where('redeemed_ticket_id', $ticket->id)->exists()) {
                throw new DomainRuleViolation(
                    'Cortesia resgatada de voucher não pode ser transferida.',
                    'not_transferable'
                );
            }

            $email = mb_strtolower(trim($participant['participant_email']));

            $new = $ticket->order->tickets()->create([
                'event_id' => $ticket->event_id,
                'ticket_type_id' => $ticket->ticket_type_id,
                'ticket_lot_id' => $ticket->ticket_lot_id,
                'participant_name' => $participant['participant_name'],
                'participant_email' => $email,
                'participant_document' => $participant['participant_document'] ?? null,
                'companion_name' => $ticket->companion_name,
                'companion_shirt_model_id' => $ticket->companion_shirt_model_id,
                'companion_shirt_size_id' => $ticket->companion_shirt_size_id,
                'shirt_model_id' => $ticket->shirt_model_id,
                'shirt_size_id' => $ticket->shirt_size_id,
                'unit_price' => $ticket->unit_price, // snapshot preservado
                'is_courtesy' => $ticket->is_courtesy,
                'status_id' => TicketStatus::idFor(TicketStatus::CONFIRMED),
                'transferred_from_ticket_id' => $ticket->id,
            ]);

            $ticket->forceFill(['transferred_to_ticket_id' => $new->id]);
            $ticket->transitionTo(TicketStatus::TRANSFERRED);

            $this->recountAround($ticket); // líquido neutro, caches coerentes

            activity('ticket.transferred')
                ->performedOn($ticket)
                ->causedBy($actor)
                ->withProperties([
                    'reference' => $ticket->code,
                    'transferredTo' => $new->code,
                    'newParticipant' => $new->participant_name,
                ])
                ->log('Ingresso '.$ticket->code.' transferido para '.$new->participant_name
                    .' ('.$new->code.')');

            return $new->fresh();
        });

        try {
            Notification::route('mail', $newTicket->participant_email)
                ->notify(new TicketTransferredPtBr($newTicket));
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar transferência', ['ticket' => $newTicket->code, 'error' => $e->getMessage()]);
        }

        return $newTicket;
    }

    public function openRefundCase(Order $order, ?Ticket $ticket, string $amount, User $actor, string $origin): SupportCase
    {
        $case = SupportCase::query()->create([
            'event_id' => $order->event_id,
            'order_id' => $order->id,
            'ticket_id' => $ticket?->id,
            'user_id' => $order->buyer_user_id,
            'type' => 'refund',
            'status' => 'open',
            'subject' => 'Reembolso — pedido '.$order->code.($ticket ? ' / ingresso '.$ticket->code : ''),
            'refund_amount' => $amount,
        ]);

        $case->notes()->create([
            'author_user_id' => $actor->id,
            'body' => $origin.' Valor a devolver: R$ '.number_format((float) $amount, 2, ',', '.'),
            'visible_to_attendee' => true,
            'from_attendee' => false,
        ]);

        return $case;
    }

    private function ensureCancellable(Event $event, ?Ticket $ticket, bool $byStaff = false): void
    {
        // A organização (staff) pode cancelar mesmo com o autoatendimento
        // desligado; o inscrito, não.
        if (! $byStaff && ! $event->allow_user_cancel) {
            throw new DomainRuleViolation(
                'Este evento não permite cancelamento pelo inscrito — abra um caso de suporte.',
                'cancel_disabled'
            );
        }

        if ($ticket !== null && ! in_array($ticket->status?->slug, TicketStatus::LIVE, true)) {
            throw new DomainRuleViolation('Este ingresso não pode mais ser cancelado.', 'terminal_status');
        }
    }

    private function isPaidTicket(Ticket $ticket): bool
    {
        return ! $ticket->is_courtesy
            && bccomp($ticket->unit_price, '0.00', 2) === 1
            && in_array($ticket->order->status?->slug, [OrderStatus::PAID, OrderStatus::PARTIALLY_PAID], true);
    }

    private function recountAround(Ticket $ticket): void
    {
        $ticket->ticketLot?->recountSold();
        $ticket->shirtSize?->recountSold();
        $ticket->companionShirtSize?->recountSold();
    }

    private function notifyQuietly(Order $order, object $notification): void
    {
        try {
            $order->buyerUser?->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail do ciclo de vida', ['order' => $order->code, 'error' => $e->getMessage()]);
        }
    }
}

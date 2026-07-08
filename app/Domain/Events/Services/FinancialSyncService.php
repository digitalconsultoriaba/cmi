<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\FinancialEntry;
use App\Domain\Events\Models\FinancialSettlement;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\SponsorshipInstallment;

/**
 * Espelho financeiro (spec 010, FR-020): cada pedido de ingresso e cada parcela
 * de patrocínio refletem UMA conta a receber, sincronizada com o estado real
 * (ponto único de baixa da 005). Upsert por (source_type, source_id) — zero
 * duplicidade. Espelhos são somente leitura na UI. Cortesias não geram receita.
 */
class FinancialSyncService
{
    /** Marca a baixa automática do espelho (não confundir com baixa manual). */
    private const AUTO_NOTE = 'Baixa automática (venda/recebimento)';

    /** Sincroniza a conta a receber espelhada de um pedido de ingresso. */
    public function syncOrder(Order $order): void
    {
        // Cortesia / total zero → não gera receita
        if (bccomp((string) $order->total_amount, '0.00', 2) !== 1) {
            $this->forget(Order::class, $order->id);

            return;
        }

        $slug = $order->status?->slug;
        $cancelled = in_array($slug, [OrderStatus::CANCELLED, OrderStatus::EXPIRED, OrderStatus::REFUNDED], true);
        $settled = $slug === OrderStatus::PAID ? (string) $order->total_amount : '0.00';

        // Data do recebimento = baixa do pagamento (paid_at); fallback coerente.
        $paidOn = $order->payments()
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PAID]))
            ->max('paid_at');

        $this->upsert(Order::class, $order->id, [
            'direction' => FinancialEntry::RECEIVABLE,
            'description' => 'Ingressos — '.($order->buyer_name ?: 'pedido '.$order->code),
            'amount' => $order->total_amount,
            'settled_amount' => $settled,
            'settled_on' => $paidOn ? \Illuminate\Support\Carbon::parse($paidOn)->toDateString() : now()->toDateString(),
            'event_id' => $order->event_id,
            'due_date' => ($order->reserved_until ?? $order->created_at ?? now())->toDateString(),
            'origin' => 'ticket',
            'cancelled' => $cancelled,
        ]);
    }

    /** Sincroniza a conta a receber espelhada de uma parcela de patrocínio. */
    public function syncSponsorshipInstallment(SponsorshipInstallment $installment): void
    {
        $sponsorship = $installment->sponsorship;
        if ($sponsorship === null) {
            return;
        }

        $cancelled = $sponsorship->status === 'cancelled';
        $settled = $installment->status === 'paid'
            ? (string) ($installment->paid_amount ?? $installment->amount) : '0.00';

        $this->upsert(SponsorshipInstallment::class, $installment->id, [
            'direction' => FinancialEntry::RECEIVABLE,
            'description' => 'Patrocínio '.$sponsorship->company_name
                .' — parcela '.$installment->number,
            'amount' => $installment->amount,
            'settled_amount' => $settled,
            'settled_on' => $installment->paid_at
                ? \Illuminate\Support\Carbon::parse($installment->paid_at)->toDateString()
                : now()->toDateString(),
            'event_id' => $sponsorship->event_id,
            'due_date' => ($installment->due_date ?? now())->toDateString(),
            'origin' => 'sponsorship',
            'cancelled' => $cancelled,
        ]);
    }

    private function upsert(string $type, int $id, array $data): void
    {
        $entry = FinancialEntry::query()->updateOrCreate(
            ['source_type' => $type, 'source_id' => $id],
            [
                'direction' => $data['direction'],
                'description' => $data['description'],
                'amount' => $data['amount'],
                'settled_amount' => $data['settled_amount'],
                'event_id' => $data['event_id'],
                'due_date' => $data['due_date'],
                'origin' => $data['origin'],
                'cancelled_at' => $data['cancelled'] ? now() : null,
                'cancel_reason' => $data['cancelled'] ? 'Origem cancelada/estornada' : null,
            ]
        );

        $this->syncAutoSettlement($entry, $data);
    }

    /**
     * Registra/atualiza a baixa automática do espelho para o valor liquidado,
     * de forma idempotente — assim vendas de ingresso e recebimentos de
     * patrocínio aparecem no "recebido no mês" (settlements), coerente com o
     * saldo realizado. Não toca em baixas manuais.
     */
    private function syncAutoSettlement(FinancialEntry $entry, array $data): void
    {
        $entry->settlements()->where('note', self::AUTO_NOTE)->delete();

        $settled = (float) ($data['settled_amount'] ?? 0);
        if ($settled <= 0 || ! empty($data['cancelled'])) {
            return;
        }

        $entry->settlements()->create([
            'amount' => $data['settled_amount'],
            'kind' => $entry->direction === FinancialEntry::RECEIVABLE
                ? FinancialSettlement::RECEIPT
                : FinancialSettlement::PAYMENT,
            'settled_on' => $data['settled_on'] ?? now()->toDateString(),
            'note' => self::AUTO_NOTE,
        ]);
    }

    private function forget(string $type, int $id): void
    {
        FinancialEntry::query()
            ->where('source_type', $type)->where('source_id', $id)->delete();
    }
}

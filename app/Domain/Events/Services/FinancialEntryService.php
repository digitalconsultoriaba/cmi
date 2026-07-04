<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\FinancialEntry;
use App\Domain\Events\Models\FinancialSettlement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras dos lançamentos financeiros (spec 010): criação (com parcelamento),
 * baixa total/parcial sob lock, edição com justificativa, cancelamento,
 * estorno. Situação é derivada no model; aqui garantimos as guardas e a
 * recontagem do cache. Movimentações vão para a trilha (activity_log).
 */
class FinancialEntryService
{
    /** Cria um lançamento (ou N parcelas). Retorna o primeiro/único. */
    public function create(array $data, User $actor): FinancialEntry
    {
        $this->assertPositive($data['amount']);

        $installments = (int) ($data['installments'] ?? 1);

        return DB::transaction(function () use ($data, $actor, $installments) {
            if ($installments <= 1) {
                $entry = FinancialEntry::query()->create($this->baseAttributes($data));
                $this->log($entry, 'financial.created', 'Lançamento criado', $actor);

                return $entry;
            }

            return $this->createInstallments($data, $installments, $actor);
        });
    }

    /** Parcelamento: N parcelas cuja soma fecha o total (resto na última). */
    private function createInstallments(array $data, int $count, User $actor): FinancialEntry
    {
        $group = (string) Str::uuid();
        $totalCents = (int) round(((float) $data['amount']) * 100);
        $baseCents = intdiv($totalCents, $count);
        $dueDates = $data['due_dates'] ?? null;
        $first = null;

        foreach (range(1, $count) as $number) {
            $cents = $number === $count ? $totalCents - $baseCents * ($count - 1) : $baseCents;

            $due = null;
            if (is_array($dueDates) && ! empty($dueDates[$number - 1])) {
                $due = Carbon::parse($dueDates[$number - 1]);
            } elseif (! empty($data['first_due_date'])) {
                $due = Carbon::parse($data['first_due_date'])->addMonthsNoOverflow($number - 1);
            } else {
                $due = Carbon::parse($data['due_date'])->addMonthsNoOverflow($number - 1);
            }

            $entry = FinancialEntry::query()->create(array_merge(
                $this->baseAttributes($data),
                [
                    'amount' => number_format($cents / 100, 2, '.', ''),
                    'due_date' => $due,
                    'installment_group' => $group,
                    'installment_number' => $number,
                    'installment_total' => $count,
                ]
            ));
            $this->log($entry, 'financial.created',
                "Parcela {$number}/{$count} criada", $actor);
            $first ??= $entry;
        }

        return $first;
    }

    /** Baixa (pagamento/recebimento), total ou parcial — sob lock. */
    public function settle(FinancialEntry $entry, array $data, User $actor): FinancialEntry
    {
        $this->assertPositive($data['amount']);

        return DB::transaction(function () use ($entry, $data, $actor) {
            $entry = FinancialEntry::query()->whereKey($entry->id)->lockForUpdate()->first();

            if ($entry->cancelled_at !== null) {
                throw new DomainRuleViolation('Lançamento cancelado não recebe baixa.', 'cancelled');
            }
            if ($entry->isMirror()) {
                throw new DomainRuleViolation(
                    'Este lançamento é espelho de ingresso/patrocínio — a baixa é feita na venda.',
                    'mirror_readonly'
                );
            }

            $balance = $entry->balance();
            if (bccomp((string) $data['amount'], $balance, 2) === 1) {
                throw new DomainRuleViolation(
                    'O valor da baixa excede o saldo restante ('.$balance.').',
                    'exceeds_balance'
                );
            }

            $entry->settlements()->create([
                'amount' => $data['amount'],
                'kind' => $entry->direction === FinancialEntry::RECEIVABLE
                    ? FinancialSettlement::RECEIPT : FinancialSettlement::PAYMENT,
                'settled_on' => $data['settled_on'] ?? now()->toDateString(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $entry->recountSettled();

            $this->log($entry, 'financial.settled',
                'Baixa de R$ '.number_format((float) $data['amount'], 2, ',', '.').
                ' ('.$entry->statusLabel().')', $actor, ['amount' => $data['amount']]);

            return $entry->fresh();
        });
    }

    /** Estorno de valor já baixado. */
    public function reverse(FinancialEntry $entry, array $data, User $actor): FinancialEntry
    {
        $this->assertPositive($data['amount']);

        return DB::transaction(function () use ($entry, $data, $actor) {
            $entry = FinancialEntry::query()->whereKey($entry->id)->lockForUpdate()->first();

            if (bccomp((string) $data['amount'], (string) $entry->settled_amount, 2) === 1) {
                throw new DomainRuleViolation('Estorno maior que o valor já baixado.', 'exceeds_settled');
            }

            $entry->settlements()->create([
                'amount' => $data['amount'],
                'kind' => FinancialSettlement::REVERSAL,
                'settled_on' => $data['settled_on'] ?? now()->toDateString(),
                'note' => $data['reason'],
            ]);
            $entry->recountSettled();

            $this->log($entry, 'financial.reversed',
                'Estorno de R$ '.number_format((float) $data['amount'], 2, ',', '.').': '.$data['reason'],
                $actor, ['amount' => $data['amount'], 'reason' => $data['reason']]);

            return $entry->fresh();
        });
    }

    /** Edição — exige justificativa se já houver baixa; espelho é read-only. */
    public function update(FinancialEntry $entry, array $data, ?string $justification, User $actor): FinancialEntry
    {
        if ($entry->isMirror()) {
            throw new DomainRuleViolation('Lançamento espelho não é editável.', 'mirror_readonly');
        }

        $hasSettlement = bccomp((string) $entry->settled_amount, '0.00', 2) === 1;
        if ($hasSettlement && blank($justification)) {
            throw new DomainRuleViolation(
                'Este lançamento já tem baixa — informe uma justificativa para editar.',
                'justification_required'
            );
        }
        if (array_key_exists('amount', $data)) {
            $this->assertPositive($data['amount']);
        }

        return DB::transaction(function () use ($entry, $data, $justification, $actor) {
            $entry->update($data);
            $this->log($entry, 'financial.updated',
                'Lançamento editado'.($justification ? ': '.$justification : ''),
                $actor, $justification ? ['justification' => $justification] : []);

            return $entry->fresh();
        });
    }

    /** Cancelamento (motivo obrigatório). */
    public function cancel(FinancialEntry $entry, string $reason, User $actor): FinancialEntry
    {
        if ($entry->cancelled_at !== null) {
            throw new DomainRuleViolation('Lançamento já cancelado.', 'already_cancelled');
        }

        return DB::transaction(function () use ($entry, $reason, $actor) {
            $entry->forceFill(['cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $this->log($entry, 'financial.cancelled', 'Cancelado: '.$reason, $actor);

            return $entry->fresh();
        });
    }

    /** Duplicata — novo lançamento em aberto, sem baixas. */
    public function duplicate(FinancialEntry $entry, User $actor): FinancialEntry
    {
        return DB::transaction(function () use ($entry, $actor) {
            $copy = FinancialEntry::query()->create([
                'direction' => $entry->direction,
                'description' => $entry->description.' (cópia)',
                'amount' => $entry->amount,
                'category_id' => $entry->category_id,
                'payment_method_id' => $entry->payment_method_id,
                'event_id' => $entry->event_id,
                'person_id' => $entry->person_id,
                'due_date' => $entry->due_date,
                'origin' => 'manual',
                'notes' => $entry->notes,
            ]);
            $this->log($copy, 'financial.created', 'Lançamento duplicado', $actor);

            return $copy;
        });
    }

    private function baseAttributes(array $data): array
    {
        return [
            'direction' => $data['direction'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'category_id' => $data['category_id'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'event_id' => $data['event_id'] ?? null,
            'person_id' => $data['person_id'] ?? null,
            'due_date' => $data['due_date'],
            'origin' => $data['origin'] ?? 'manual',
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function assertPositive($amount): void
    {
        if (bccomp((string) $amount, '0.00', 2) !== 1) {
            throw new DomainRuleViolation('O valor deve ser positivo.', 'invalid_amount');
        }
    }

    private function log(FinancialEntry $entry, string $name, string $desc, User $actor, array $props = []): void
    {
        activity($name)->performedOn($entry)->causedBy($actor)
            ->withProperties(array_merge(['reference' => 'FIN-'.$entry->id], $props))
            ->log($desc);
    }
}

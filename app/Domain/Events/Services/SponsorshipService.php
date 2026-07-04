<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Sponsorship;
use App\Domain\Events\Models\SponsorshipInstallment;
use Illuminate\Support\Facades\DB;

class SponsorshipService
{
    /**
     * Cria o patrocínio com N parcelas cuja soma fecha exatamente o total
     * (divisão igual em centavos; resto na última parcela).
     */
    public function createWithInstallments(Event $event, array $data): Sponsorship
    {
        return DB::transaction(function () use ($event, $data) {
            $count = (int) $data['installments_count'];

            $sponsorship = $event->sponsorships()->create([
                'company_name' => $data['company_name'],
                'contact' => $data['contact'] ?? null,
                'total_amount' => $data['total_amount'],
                'payment_method' => $data['payment_method'] ?? null,
                'installments_count' => $count,
                'notes' => $data['notes'] ?? null,
            ]);

            $totalCents = (int) round(((float) $data['total_amount']) * 100);
            $baseCents = intdiv($totalCents, $count);

            foreach (range(1, $count) as $number) {
                $cents = $number === $count
                    ? $totalCents - $baseCents * ($count - 1)
                    : $baseCents;

                $sponsorship->installments()->create([
                    'number' => $number,
                    'amount' => number_format($cents / 100, 2, '.', ''),
                    'due_date' => $data['first_due_date'] ?? null
                        ? \Illuminate\Support\Carbon::parse($data['first_due_date'])->addMonthsNoOverflow($number - 1)
                        : null,
                ]);
            }

            return $sponsorship->load('installments');
        });
    }

    /** Baixa de parcela: pendente → paga, com trilha; repetir → 409. */
    public function payInstallment(SponsorshipInstallment $installment, array $data): SponsorshipInstallment
    {
        if ($installment->sponsorship->status === 'cancelled') {
            throw new DomainRuleViolation('Patrocínio cancelado não recebe baixa.', 'terminal_status');
        }

        if ($installment->status === 'paid') {
            throw new DomainRuleViolation('Esta parcela já foi paga.', 'already_paid');
        }

        return DB::transaction(function () use ($installment, $data) {
            $installment->forceFill([
                'status' => 'paid',
                'paid_at' => $data['paid_at'] ?? now(),
                'paid_amount' => $data['paid_amount'] ?? $installment->amount,
                'method' => $data['method'] ?? null,
                'registered_by' => auth()->id(),
                'note' => $data['note'] ?? $installment->note,
            ])->save();

            $installment->sponsorship->recalculateStatus();

            activity('sponsorship.installment_paid')
                ->performedOn($installment)
                ->withProperties([
                    'reference' => $installment->sponsorship->company_name,
                    'number' => $installment->number,
                    'amount' => $installment->paid_amount,
                ])
                ->log('Parcela '.$installment->number.' do patrocínio de '
                    .$installment->sponsorship->company_name.' recebida (R$ '
                    .number_format((float) $installment->paid_amount, 2, ',', '.').')');

            return $installment->fresh();
        });
    }
}

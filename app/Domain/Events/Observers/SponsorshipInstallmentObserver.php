<?php

namespace App\Domain\Events\Observers;

use App\Domain\Events\Models\SponsorshipInstallment;
use App\Domain\Events\Services\FinancialSyncService;

/** Espelha a parcela de patrocínio numa conta a receber (spec 010, FR-020). */
class SponsorshipInstallmentObserver
{
    public function __construct(private readonly FinancialSyncService $sync) {}

    public function saved(SponsorshipInstallment $installment): void
    {
        $this->sync->syncSponsorshipInstallment($installment->fresh(['sponsorship']) ?? $installment);
    }
}

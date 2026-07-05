<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\BudgetCostItem;
use App\Domain\Events\Models\BudgetSponsorship;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\FinancialCategory;
use App\Domain\Events\Models\FinancialEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Converte previsões do orçamento em lançamentos reais do Financeiro (spec 011),
 * reutilizando o ponto único de criação (FinancialEntryService). Idempotente:
 * uma linha já convertida não gera outro lançamento (409).
 */
class BudgetConversionService
{
    public function __construct(private readonly FinancialEntryService $entries)
    {
    }

    /** Item de custo previsto → conta a pagar do evento. */
    public function toPayable(BudgetCostItem $item, Event $event, User $actor): FinancialEntry
    {
        if ($item->financial_entry_id !== null) {
            throw new DomainRuleViolation('Este item já gerou uma conta a pagar.', 'already_converted');
        }

        return DB::transaction(function () use ($item, $event, $actor) {
            $entry = $this->entries->create([
                'direction' => FinancialEntry::PAYABLE,
                'description' => $item->description,
                'amount' => $item->total_amount,
                'category_id' => $this->matchCategory($item->category, FinancialCategory::EXPENSE),
                'event_id' => $event->id,
                'due_date' => $event->starts_at ?? now(),
                'origin' => 'event_expense',
                'notes' => $item->notes,
            ], $actor);

            $item->forceFill(['financial_entry_id' => $entry->id])->save();

            return $entry;
        });
    }

    /** Cota de patrocínio prevista → conta a receber do evento. */
    public function toReceivable(BudgetSponsorship $sponsorship, Event $event, User $actor): FinancialEntry
    {
        if ($sponsorship->financial_entry_id !== null) {
            throw new DomainRuleViolation('Este patrocínio já gerou uma conta a receber.', 'already_converted');
        }
        if ($sponsorship->isExcluded()) {
            throw new DomainRuleViolation(
                'Patrocínio perdido ou cancelado não gera conta a receber.',
                'invalid_sponsorship_status'
            );
        }

        return DB::transaction(function () use ($sponsorship, $event, $actor) {
            $entry = $this->entries->create([
                'direction' => FinancialEntry::RECEIVABLE,
                'description' => $sponsorship->name,
                'amount' => $sponsorship->expectedRevenue(),
                'category_id' => $this->matchCategory('Patrocínios', FinancialCategory::INCOME),
                'event_id' => $event->id,
                'due_date' => $event->starts_at ?? now(),
                'origin' => 'sponsorship',
                'notes' => $sponsorship->notes,
            ], $actor);

            $sponsorship->forceFill(['financial_entry_id' => $entry->id])->save();

            return $entry;
        });
    }

    /** Resolve categoria financeira por nome (mesma direção); null se ausente. */
    private function matchCategory(string $name, string $direction): ?int
    {
        return FinancialCategory::query()
            ->where('direction', $direction)
            ->where('name', $name)
            ->value('id');
    }
}

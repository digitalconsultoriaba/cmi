<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\BudgetPlan;
use App\Domain\Events\Models\FinancialEntry;

/**
 * Comparativo orçado × realizado (spec 011). O lado "realizado" vem do
 * Financeiro real (spec 010) e das vendas reais do evento — sem reimplementar
 * regra financeira.
 */
class BudgetComparisonService
{
    public function __construct(
        private readonly BudgetCalculator $calculator,
        private readonly FinancialReportService $reports,
    ) {
    }

    /** @return array<string,mixed> */
    public function compare(BudgetPlan $plan): array
    {
        $event = $plan->event;
        $summary = $this->calculator->summary($plan);
        $real = $this->reports->eventResult($event);

        $budgetedCost = (float) $summary['totalCost'];
        $actualCost = (float) $real['despesaRealizada'];
        $budgetedRevenue = (float) $summary['totalRevenue'];
        $actualRevenue = (float) $real['receitaRealizada'];

        // Patrocínio recebido: baixas de lançamentos de origem sponsorship do evento.
        $actualSponsorship = (string) FinancialEntry::query()
            ->where('event_id', $event->id)
            ->where('origin', 'sponsorship')
            ->sum('settled_amount');

        // Ingressos previstos (meta) × vendidos reais.
        $budgetedTickets = (int) $plan->ticketLots->sum('expected_quantity');
        $actualTickets = $event->ticketsSold();
        $attainment = $budgetedTickets > 0
            ? number_format($actualTickets / $budgetedTickets * 100, 2, '.', '')
            : null;

        $money = fn ($v) => number_format((float) $v, 2, '.', '');
        $costStatus = $actualCost < $budgetedCost ? 'under' : ($actualCost > $budgetedCost ? 'over' : 'on');

        return [
            'cost' => [
                'budgeted' => $money($budgetedCost),
                'actual' => $money($actualCost),
                'status' => $costStatus,
            ],
            'revenue' => [
                'budgeted' => $money($budgetedRevenue),
                'actual' => $money($actualRevenue),
                'diff' => $money($budgetedRevenue - $actualRevenue),
            ],
            'sponsorship' => [
                'budgeted' => $summary['sponsorshipExpected'],
                'actual' => $money($actualSponsorship),
                'diff' => $money((float) $summary['sponsorshipExpected'] - (float) $actualSponsorship),
            ],
            'tickets' => [
                'budgeted' => $budgetedTickets,
                'actual' => $actualTickets,
                'attainmentPct' => $attainment,
            ],
            'result' => [
                'budgeted' => $summary['result'],
                'actual' => $money($actualRevenue - $actualCost),
            ],
        ];
    }
}

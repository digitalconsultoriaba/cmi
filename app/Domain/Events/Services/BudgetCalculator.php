<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\BudgetCostItemStatus;
use App\Domain\Events\Models\BudgetPlan;
use App\Domain\Events\Models\BudgetSponsorshipStatus;

/**
 * Deriva TODOS os indicadores do orçamento (spec 011) — nunca persistidos
 * (constituição II). Trata divisores zero devolvendo null (UI mostra "—").
 */
class BudgetCalculator
{
    /** @return array<string,mixed> */
    public function summary(BudgetPlan $plan): array
    {
        $money = fn ($v) => number_format((float) $v, 2, '.', '');

        // Custo total previsto = itens não cancelados.
        $totalCost = $plan->costItems
            ->reject(fn ($i) => $i->status === BudgetCostItemStatus::CANCELLED)
            ->sum(fn ($i) => (float) $i->total_amount);

        // Receita prevista com ingressos.
        $ticketRevenue = $plan->ticketLots
            ->sum(fn ($l) => (float) $l->unit_price * (int) $l->expected_quantity);

        // Patrocínio: previsto total (exceto lost/cancelled) e confirmado.
        $sponsorshipExpected = $plan->sponsorships
            ->reject(fn ($s) => in_array($s->status, BudgetSponsorshipStatus::EXCLUDED, true))
            ->sum(fn ($s) => (float) $s->unit_value * (int) $s->quantity);
        $sponsorshipConfirmed = $plan->sponsorships
            ->filter(fn ($s) => in_array($s->status, BudgetSponsorshipStatus::CONFIRMED_SET, true))
            ->sum(fn ($s) => (float) $s->unit_value * (int) $s->quantity);

        $otherRevenue = (float) $plan->other_revenue;
        $totalRevenue = $ticketRevenue + $sponsorshipExpected + $otherRevenue;
        $result = $totalRevenue - $totalCost;
        $ownInvestment = max(0, $totalCost - $totalRevenue);

        $paying = (int) $plan->expected_paying;
        $participants = $plan->totalParticipants();

        $avgTicket = $paying > 0 ? $ticketRevenue / $paying : null;
        $costPerParticipant = $participants > 0 ? $totalCost / $participants : null;
        $costPerPaying = $paying > 0 ? $totalCost / $paying : null;

        // Ponto de equilíbrio (pagantes): valor a cobrir ÷ ticket médio.
        $toCover = $totalCost - $sponsorshipExpected - $otherRevenue;
        $breakEven = $avgTicket !== null && $avgTicket > 0
            ? (int) ceil(max(0, $toCover) / $avgTicket)
            : null;

        $margin = $plan->safety_margin_pct !== null ? (float) $plan->safety_margin_pct : null;
        $costWithMargin = $margin !== null ? $totalCost * (1 + $margin / 100) : null;

        $classification = $result > 0 ? 'surplus' : ($result < 0 ? 'deficit' : 'breakeven');

        return [
            'totalCost' => $money($totalCost),
            'ticketRevenue' => $money($ticketRevenue),
            'sponsorshipExpected' => $money($sponsorshipExpected),
            'sponsorshipConfirmed' => $money($sponsorshipConfirmed),
            'otherRevenue' => $money($otherRevenue),
            'totalRevenue' => $money($totalRevenue),
            'result' => $money($result),
            'classification' => $classification,
            'amountMissing' => $money($ownInvestment),
            'ownInvestment' => $money($ownInvestment),
            'avgTicket' => $avgTicket !== null ? $money($avgTicket) : null,
            'costPerParticipant' => $costPerParticipant !== null ? $money($costPerParticipant) : null,
            'costPerPaying' => $costPerPaying !== null ? $money($costPerPaying) : null,
            'breakEvenPaying' => $breakEven,
            'costWithMargin' => $costWithMargin !== null ? $money($costWithMargin) : null,
            'totalParticipants' => $participants,
            'alerts' => $this->alerts($plan, [
                'result' => $result,
                'ownInvestment' => $ownInvestment,
                'totalCost' => $totalCost,
                'sponsorshipConfirmed' => $sponsorshipConfirmed,
                'avgTicket' => $avgTicket,
                'costPerPaying' => $costPerPaying,
                'margin' => $margin,
            ]),
        ];
    }

    /**
     * Simulador de preço mínimo do ingresso (função pura, não persiste).
     * valor mínimo = (custo − patrocínio − outras) ÷ pagantes.
     */
    public function minimumTicketPrice(float $cost, float $sponsorship, float $otherRevenue, int $paying): ?string
    {
        if ($paying <= 0) {
            return null;
        }
        $toCover = max(0, $cost - $sponsorship - $otherRevenue);

        return number_format($toCover / $paying, 2, '.', '');
    }

    /** @return array<int,array{level:string,message:string}> */
    private function alerts(BudgetPlan $plan, array $m): array
    {
        $alerts = [];
        $fmt = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');

        if ($m['result'] < 0) {
            $alerts[] = ['level' => 'danger', 'message' => "Faltam {$fmt($m['ownInvestment'])} para fechar o orçamento do evento."];
        } elseif ($m['result'] > 0) {
            $alerts[] = ['level' => 'info', 'message' => 'Superávit previsto — a receita prevista cobre o custo.'];
        }

        if ($m['ownInvestment'] > 0) {
            $alerts[] = ['level' => 'warning', 'message' => "O evento depende de investimento próprio de {$fmt($m['ownInvestment'])}."];
        }

        if ($m['sponsorshipConfirmed'] < $m['totalCost']) {
            $alerts[] = ['level' => 'warning', 'message' => 'O patrocínio confirmado ainda não cobre o custo previsto.'];
        }

        if ($m['avgTicket'] !== null && $m['costPerPaying'] !== null && $m['costPerPaying'] > $m['avgTicket']) {
            $alerts[] = ['level' => 'warning', 'message' => 'O custo por pagante está acima do ticket médio previsto.'];
        }

        if ($m['margin'] === null) {
            $alerts[] = ['level' => 'info', 'message' => 'O orçamento está sem margem de segurança definida.'];
        }

        $unconvertedItems = $plan->costItems
            ->reject(fn ($i) => $i->status === BudgetCostItemStatus::CANCELLED)
            ->whereNull('financial_entry_id')->count();
        if ($unconvertedItems > 0) {
            $alerts[] = ['level' => 'info', 'message' => "Existem {$unconvertedItems} item(ns) de custo ainda não convertidos em conta a pagar."];
        }

        $unconvertedSponsors = $plan->sponsorships
            ->filter(fn ($s) => in_array($s->status, BudgetSponsorshipStatus::CONFIRMED_SET, true))
            ->whereNull('financial_entry_id')->count();
        if ($unconvertedSponsors > 0) {
            $alerts[] = ['level' => 'info', 'message' => "Existem {$unconvertedSponsors} patrocínio(s) confirmado(s) ainda não convertidos em conta a receber."];
        }

        return $alerts;
    }
}

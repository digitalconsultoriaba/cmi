<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\BudgetPlan;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\BudgetCalculator;
use App\Domain\Events\Services\BudgetComparisonService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBudgetPlanRequest;
use App\Http\Resources\Admin\BudgetPlanResource;
use App\Support\ApiResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Aba Orçamento do evento (spec 011). Cria o plano sob demanda e devolve o
 * orçamento completo com o resumo derivado. Acesso admin/treasury.
 */
class BudgetController extends Controller
{
    public function __construct(private readonly BudgetComparisonService $comparison)
    {
    }

    public function show(Event $event)
    {
        return BudgetPlanResource::make($this->planFor($event));
    }

    public function update(UpdateBudgetPlanRequest $request, Event $event)
    {
        $plan = $this->planFor($event);
        $plan->forceFill($request->columns())->save();

        return BudgetPlanResource::make($plan->fresh()->load([
            'costItems', 'ticketLots', 'sponsorships', 'scenarios',
        ]));
    }

    public function comparison(Event $event)
    {
        return ApiResponse::data($this->comparison->compare($this->planFor($event)));
    }

    public function exportXlsx(Event $event)
    {
        $plan = $this->planFor($event);
        $summary = app(BudgetCalculator::class)->summary($plan);

        return response()->streamDownload(function () use ($plan, $summary, $event) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues(['Orçamento — '.$event->name]));
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues(['Resumo', 'Valor']));
            foreach ([
                'Custo total previsto' => $summary['totalCost'],
                'Receita prevista (ingressos)' => $summary['ticketRevenue'],
                'Receita prevista (patrocínios)' => $summary['sponsorshipExpected'],
                'Receita total prevista' => $summary['totalRevenue'],
                'Resultado previsto' => $summary['result'],
                'Investimento próprio' => $summary['ownInvestment'],
            ] as $label => $value) {
                $writer->addRow(Row::fromValues([$label, $value]));
            }

            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues(['Itens de custo', 'Categoria', 'Qtd', 'Unitário', 'Total', 'Status']));
            foreach ($plan->costItems as $i) {
                $writer->addRow(Row::fromValues([
                    $i->description, $i->category, $i->quantity ?? '', $i->unit_price ?? '', $i->total_amount, $i->status,
                ]));
            }

            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues(['Lotes previstos', 'Valor', 'Qtd prevista', 'Receita prevista']));
            foreach ($plan->ticketLots as $l) {
                $writer->addRow(Row::fromValues([$l->name, $l->unit_price, $l->expected_quantity, $l->expectedRevenue()]));
            }

            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues(['Patrocínios previstos', 'Valor', 'Qtd', 'Status', 'Receita prevista']));
            foreach ($plan->sponsorships as $s) {
                $writer->addRow(Row::fromValues([$s->name, $s->unit_value, $s->quantity, $s->status, $s->expectedRevenue()]));
            }

            $writer->close();
        }, "orcamento-{$event->slug}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(Event $event)
    {
        $plan = $this->planFor($event);
        $summary = app(BudgetCalculator::class)->summary($plan);
        $comparison = $this->comparison->compare($plan);

        $html = view('reports.budget', compact('event', 'plan', 'summary', 'comparison'))->render();

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->download("orcamento-{$event->slug}.pdf");
    }

    /** Plano do evento (firstOrCreate) com os filhos carregados. */
    private function planFor(Event $event): BudgetPlan
    {
        BudgetPlan::query()->firstOrCreate(['event_id' => $event->id]);

        // Re-busca uma instância "não recém-criada" (evita o 201 automático do
        // JsonResource no GET quando o plano acabou de ser criado).
        return BudgetPlan::query()
            ->with(['costItems', 'ticketLots', 'sponsorships', 'scenarios'])
            ->where('event_id', $event->id)
            ->first();
    }
}

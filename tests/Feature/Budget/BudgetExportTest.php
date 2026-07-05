<?php

namespace Tests\Feature\Budget;

/**
 * Spec 011 — exportação do orçamento (xlsx/pdf).
 */
class BudgetExportTest extends BudgetTestCase
{
    public function test_exporta_xlsx_e_pdf(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Item', 'category' => 'Outros', 'totalAmount' => '100.00',
        ])->assertCreated();

        $xlsx = $this->actingAs($admin)->get($this->budgetUrl('/export.xlsx'));
        $xlsx->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml', $xlsx->headers->get('content-type')
        );

        $pdf = $this->actingAs($admin)->get($this->budgetUrl('/export.pdf'));
        $pdf->assertOk();
        $this->assertStringContainsString('pdf', strtolower($pdf->headers->get('content-type')));
    }
}

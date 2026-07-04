<?php

namespace Tests\Feature\Reports;

use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US3 — planilhas .xlsx geradas do MESMO service das telas (spec 008):
 * o teste ABRE o binário com o reader e confere as linhas.
 */
class ExportTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    /** Baixa a rota e devolve as linhas da planilha como arrays de células. */
    private function download($user, string $uri): array
    {
        $response = $this->actingAs($user)->get($uri);

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(
                    fn ($cell) => is_object($cell) ? (string) $cell : $cell,
                    $row->toArray()
                );
            }
        }
        $reader->close();
        @unlink($path);

        return $rows;
    }

    public function test_inscritos_tem_uma_linha_por_pessoa_e_segue_a_regua_da_portaria(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();

        // 1 individual + 1 casal pagos; 1 pedido pendente (fora); 1 cancelado (fora)
        $code = $this->buy($buyer, [
            $this->item($this->individual, ['participant_name' => 'Solo Da Silva']),
            $this->item($this->couple, [
                'participant_name' => 'Titular Casal', 'companion_name' => 'Par Casal',
            ]),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $other = $this->buyer();
        $this->buy($other, [$this->item($this->individual, ['participant_name' => 'Pendente Fora'])]);

        [, $paidOrder2] = [null, null];
        $cancelBuyer = $this->buyer();
        $cancelCode = $this->buy($cancelBuyer, [
            $this->item($this->individual, ['participant_name' => 'Cancelado Fora']),
        ])->json('data.orders.0.code');
        $this->actingAs($cancelBuyer)->postJson("/api/orders/{$cancelCode}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();
        $cancelled = \App\Domain\Events\Models\Order::query()->where('code', $cancelCode)
            ->first()->tickets->first();
        $this->actingAs($cancelBuyer)->postJson("/api/tickets/{$cancelled->code}/cancel")->assertOk();

        $rows = $this->download($this->admin(), '/api/admin/reports/attendees.xlsx');

        $names = array_column(array_slice($rows, 1), 0);
        $this->assertCount(3, $names, '3 PESSOAS elegíveis = 3 linhas');
        $this->assertContains('Solo Da Silva', $names);
        $this->assertContains('Titular Casal', $names);
        $this->assertContains('Par Casal', $names, 'acompanhante tem linha própria');
        $this->assertNotContains('Pendente Fora', $names);
        $this->assertNotContains('Cancelado Fora', $names);

        // O acompanhante vem marcado como tal
        $companionRow = collect(array_slice($rows, 1))->firstWhere(0, 'Par Casal');
        $this->assertSame('Acompanhante', $companionRow[1]);
    }

    public function test_financeiro_respeita_o_filtro_e_traz_estornos(): void
    {
        [$buyer, $order] = $this->paidOrder();

        // Estorno total para popular a seção de estornos
        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$order->tickets->first()->code}/cancel")->assertOk();
        $case = \App\Domain\Events\Models\SupportCase::query()
            ->where('type', 'refund')->firstOrFail();
        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);
        $this->actingAs($treasury)->postJson("/api/treasury/refunds/{$case->id}/execute", [
            'justification' => 'Cancelamento dentro da política',
        ])->assertOk();

        $rows = $this->download($treasury, '/api/treasury/reports/finance.xlsx');
        $flat = collect($rows)->flatten()->filter()->values();

        $this->assertTrue($flat->contains($order->code), 'pagamento listado');
        $this->assertTrue($flat->contains('Estornos'), 'seção de estornos presente');

        // Período sem movimento → planilha só com cabeçalhos/seções (sem o pedido)
        $empty = $this->download($treasury,
            '/api/treasury/reports/finance.xlsx?from=2030-01-01&to=2030-01-31');
        $this->assertFalse(
            collect($empty)->flatten()->contains($order->code),
            'filtro do export = filtro da tela'
        );
    }

    public function test_presencas_lista_elegiveis_com_entrada(): void
    {
        [, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();

        $gate = $this->buyer();
        $gate->assignRole(Role::GATE);
        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $ticket->code])->assertOk();

        $rows = $this->download($this->admin(), '/api/admin/reports/attendance.xlsx');
        $dataRow = collect(array_slice($rows, 1))->firstWhere(0, $ticket->code);

        $this->assertNotNull($dataRow);
        $this->assertSame($gate->name, end($dataRow), 'validado por');
    }

    public function test_rbac_dos_exports(): void
    {
        // Anônimo primeiro
        $this->get('/api/admin/reports/attendees.xlsx',
            ['Accept' => 'application/json'])->assertStatus(401);

        $this->sellableEvent();

        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);

        // Inscritos/presenças: só admin (treasury barrado)
        $this->actingAs($treasury)->get('/api/admin/reports/attendees.xlsx',
            ['Accept' => 'application/json'])->assertStatus(403);
        $this->actingAs($treasury)->get('/api/admin/reports/attendance.xlsx',
            ['Accept' => 'application/json'])->assertStatus(403);

        // Financeiro: treasury E admin passam; attendee barrado
        $this->actingAs($treasury)->get('/api/treasury/reports/finance.xlsx')->assertOk();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/api/treasury/reports/finance.xlsx')->assertOk();
        $this->actingAs($this->buyer())->get('/api/treasury/reports/finance.xlsx',
            ['Accept' => 'application/json'])->assertStatus(403);
    }
}

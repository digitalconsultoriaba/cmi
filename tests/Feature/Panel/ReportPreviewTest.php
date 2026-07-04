<?php

namespace Tests\Feature\Panel;

use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US5 — relatórios com prévia + export do mesmo recorte (spec 009).
 */
class ReportPreviewTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    /** Abre o binário e devolve as linhas de dados (sem cabeçalho). */
    private function xlsxRows($response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($c) => is_object($c) ? (string) $c : $c, $row->toArray());
            }
        }
        $reader->close();
        @unlink($path);

        return array_slice($rows, 1); // sem cabeçalho
    }

    public function test_previa_e_export_do_mesmo_recorte_batem(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [
            $this->item($this->individual, ['participant_name' => 'Solo Da Silva']),
            $this->item($this->couple, ['participant_name' => 'Titular', 'companion_name' => 'Par']),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $admin = $this->admin();
        $eventId = $this->event->id;

        // Prévia de inscritos: 3 pessoas → mas a lista de inscritos é por
        // ingresso (2 linhas: individual + casal); confere colunas e total
        $preview = $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/reports/preview?type=inscritos")->assertOk();
        $preview->assertJsonPath('data.columns.0', 'Participante');
        $this->assertSame(2, $preview->json('data.total'));

        // Export do MESMO recorte traz as mesmas linhas
        $export = $this->actingAs($admin)
            ->get("/api/admin/events/{$eventId}/reports/inscritos.xlsx")->assertOk();
        $this->assertStringContainsString('spreadsheet', $export->headers->get('content-type'));
        $rows = $this->xlsxRows($export);
        $this->assertCount(2, $rows);
        $this->assertSame($preview->json('data.total'), count($rows));
    }

    public function test_tipo_invalido_e_filtro_vazio(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();
        $eventId = $this->event->id;

        // type desconhecido → 422
        $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/reports/preview?type=xpto")
            ->assertUnprocessable();

        // export com type inválido na rota → 404
        $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/reports/xpto.xlsx")
            ->assertNotFound();

        // filtro sem resultados → 0 linhas, sem erro
        $empty = $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/reports/preview?type=inscritos&search=inexistente")
            ->assertOk();
        $this->assertSame(0, $empty->json('data.total'));
        $this->assertSame([], $empty->json('data.rows'));
    }

    public function test_reports_exigem_admin(): void
    {
        $this->sellableEvent();
        $eventId = $this->event->id;

        $this->getJson("/api/admin/events/{$eventId}/reports/preview?type=inscritos")
            ->assertStatus(401);

        // Financeiro acessa tudo (spec 009); inscrito comum não
        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);
        $this->actingAs($treasury)
            ->getJson("/api/admin/events/{$eventId}/reports/preview?type=inscritos")
            ->assertOk();

        $this->actingAs($this->buyer())
            ->getJson("/api/admin/events/{$eventId}/reports/preview?type=inscritos")
            ->assertStatus(403);
    }
}

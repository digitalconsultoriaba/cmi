<?php

namespace Tests\Feature\Lifecycle;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\TicketStatus;
use App\Models\User;
use App\Notifications\TicketTransferredPtBr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * US2 — transferência por e-mail (quickstart §US2).
 */
class TransferTest extends LifecycleTestCase
{
    use RefreshDatabase;

    public function test_transfere_com_vinculos_bidirecionais_e_snapshot_herdado(): void
    {
        Notification::fake();
        [$buyer, $order] = $this->paidOrder();
        $original = $order->tickets->first();
        $seatsBefore = $this->event->fresh()->ticketsSold();

        $response = $this->actingAs($buyer)
            ->postJson("/api/tickets/{$original->code}/transfer", [
                'participant_name' => 'Novo Titular',
                'participant_email' => 'NOVO@Titular.com',
            ])->assertCreated();

        $newCode = $response->json('data.code');
        $this->assertNotSame($original->code, $newCode);
        $this->assertSame('confirmed', $response->json('data.status'));
        $this->assertSame('200.00', $response->json('data.unitPrice'), 'snapshot herdado');

        $freshOriginal = $original->fresh();
        $this->assertSame(TicketStatus::TRANSFERRED, $freshOriginal->status->slug);
        $this->assertNotNull($freshOriginal->transferred_to_ticket_id);
        $this->assertSame($original->id, $freshOriginal->transferredTo->transferred_from_ticket_id);

        // Vagas líquidas inalteradas
        $this->assertSame($seatsBefore, $this->event->fresh()->ticketsSold());

        // E-mail ao novo titular (normalizado)
        Notification::assertSentOnDemand(TicketTransferredPtBr::class);
    }

    public function test_destinatario_com_conta_ve_o_ingresso_via_claim(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $recipient = User::factory()->create(['email' => 'destino@x.com']);

        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$order->tickets->first()->code}/transfer", [
                'participant_name' => 'Destino',
                'participant_email' => 'destino@x.com',
            ])->assertCreated();

        $response = $this->actingAs($recipient)->getJson('/api/tickets')->assertOk();

        $mine = collect($response->json('data'))->firstWhere('participantName', 'Destino');
        $this->assertNotNull($mine);
        $this->assertTrue($mine['receiptAvailable']);
    }

    public function test_guardas_de_transferencia(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();
        $payload = ['participant_name' => 'X', 'participant_email' => 'x@x.com'];

        // Flag desabilitada
        $this->event->update(['allow_transfer' => false]);
        $this->actingAs($buyer)->postJson("/api/tickets/{$ticket->code}/transfer", $payload)
            ->assertStatus(409)->assertJsonPath('type', 'not_transferable');
        $this->event->update(['allow_transfer' => true]);

        // Evento já iniciado
        Carbon::setTestNow($this->event->starts_at->copy()->addHour());
        $this->actingAs($buyer)->postJson("/api/tickets/{$ticket->code}/transfer", $payload)
            ->assertStatus(409);
        Carbon::setTestNow();

        // Não-dono
        $this->actingAs($this->buyer())->postJson("/api/tickets/{$ticket->code}/transfer", $payload)
            ->assertStatus(403);

        // Transfere; retransferir o antigo → 409 (terminal)
        $this->actingAs($buyer)->postJson("/api/tickets/{$ticket->code}/transfer", $payload)
            ->assertCreated();
        $this->actingAs($buyer)->postJson("/api/tickets/{$ticket->code}/transfer", $payload)
            ->assertStatus(409);
    }

    public function test_reservado_e_voucher_nao_transferem(): void
    {
        // Reservado (não pago)
        $this->sellableEvent(['allow_transfer' => true]);
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])
            ->json('data.orders.0.tickets.0.code');

        $this->actingAs($buyer)->postJson("/api/tickets/{$code}/transfer", [
            'participant_name' => 'X', 'participant_email' => 'x@x.com',
        ])->assertStatus(409)->assertJsonPath('type', 'not_transferable');

        // Cortesia resgatada de voucher
        \App\Domain\Events\Models\TicketType::factory()->create([
            'event_id' => $this->event->id, 'is_courtesy' => true, 'price' => '0.00',
        ]);
        $voucher = CourtesyVoucher::query()->create([
            'event_id' => $this->event->id,
            'status' => CourtesyVoucher::DISTRIBUTED,
        ]);
        $voucherTicketCode = $this->buy($buyer, [], ['voucher_code' => $voucher->code])
            ->json('data.orders.0.tickets.0.code');

        $this->actingAs($buyer)->postJson("/api/tickets/{$voucherTicketCode}/transfer", [
            'participant_name' => 'X', 'participant_email' => 'x@x.com',
        ])->assertStatus(409)->assertJsonPath('type', 'not_transferable');
    }
}

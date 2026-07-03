<?php

namespace Tests\Feature\Purchase;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US4 — meus pedidos/ingressos com escopo de dono (quickstart §US4).
 */
class MyAreaTest extends PurchaseTestCase
{
    use RefreshDatabase;

    public function test_meus_pedidos_lista_so_os_meus_com_tickets(): void
    {
        $this->sellableEvent();
        $me = $this->buyer();
        $other = $this->buyer();

        $this->buy($me, [$this->item($this->individual)])->assertCreated();
        $this->buy($other, [$this->item($this->individual)])->assertCreated();

        $response = $this->actingAs($me)->getJson('/api/orders')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertCount(1, $response->json('data.0.tickets'));
        $this->assertNotNull($response->json('data.0.reservedUntil'));
    }

    public function test_pedido_de_outro_por_code_da_403(): void
    {
        $this->sellableEvent();
        $owner = $this->buyer();

        // Pedido criado via service (sem HTTP) para testar anônimo → 401 limpo
        $orders = app(\App\Domain\Events\Services\TicketPurchaseService::class)
            ->purchase($this->event, $owner, [$this->item($this->individual)]);
        $code = $orders[0]->code;

        $this->getJson("/api/orders/{$code}")->assertStatus(401);

        $this->actingAs($this->buyer())->getJson("/api/orders/{$code}")
            ->assertStatus(403)->assertJsonPath('type', 'forbidden');

        $this->actingAs($owner)->getJson("/api/orders/{$code}")->assertOk();
    }

    public function test_meus_ingressos_inclui_emitidos_para_meu_email_com_claim(): void
    {
        $this->sellableEvent();
        $buyerA = $this->buyer();
        $participant = User::factory()->create(['email' => 'participante@x.com']);

        // Outra pessoa compra um ingresso em nome do meu e-mail
        $this->buy($buyerA, [
            $this->item($this->individual, [
                'participant_name' => 'Participante Convidado',
                'participant_email' => 'PARTICIPANTE@X.com', // normalizado na criação
            ]),
        ])->assertCreated();

        $response = $this->actingAs($participant)->getJson('/api/tickets')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Participante Convidado', $response->json('data.0.participantName'));

        // Claim preguiçoso preencheu o vínculo
        $this->assertSame(
            $participant->id,
            $this->event->tickets()->first()->participant_user_id
        );
    }

    public function test_comprador_ve_os_ingressos_que_comprou_para_terceiros(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();

        $this->buy($buyer, [
            $this->item($this->individual, ['participant_name' => 'Sem Conta']),
        ])->assertCreated();

        $response = $this->actingAs($buyer)->getJson('/api/tickets')->assertOk();
        $this->assertCount(1, $response->json('data'));

        $code = $response->json('data.0.code');
        $this->actingAs($buyer)->getJson("/api/tickets/{$code}")->assertOk();

        // Estranho não acessa
        $this->actingAs($this->buyer())->getJson("/api/tickets/{$code}")->assertStatus(403);
    }
}

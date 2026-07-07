<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\EventStatus;
use App\Models\User;

/** US1 — checkout guest multi-participante (quickstart §Fluxo 1). */
class GuestCheckoutTest extends CheckoutTestCase
{
    public function test_guest_cria_pedido_sem_auth_com_snapshot(): void
    {
        $this->seminarEvent();

        $resp = $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['participant_name' => 'Irmão 1', 'participant_email' => 'i1@ex.com']),
            $this->item(['participant_name' => 'Irmão 2', 'participant_email' => 'i2@ex.com']),
        ]))->assertCreated();

        $resp->assertJsonPath('data.payment.required', true)
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.totalAmount', '500.00');

        $tickets = $resp->json('data.order.tickets');
        $this->assertCount(2, $tickets);
        $this->assertSame('glmees', $tickets[0]['participantCategoryKey']);
        $this->assertSame('Loja A', $tickets[0]['participantFields']['loja']);

        // Contas de comprador e participantes criadas.
        $this->assertDatabaseHas('users', ['email' => 'comprador@ex.com']);
        $this->assertDatabaseHas('users', ['email' => 'i1@ex.com']);
        $this->assertDatabaseHas('users', ['email' => 'i2@ex.com']);
    }

    public function test_config_expoe_tipos_categorias_afiliacoes(): void
    {
        $this->seminarEvent();

        $this->getJson("/api/public/events/{$this->event->slug}/checkout-config")
            ->assertOk()
            ->assertJsonPath('data.categories.0.key', 'glmees')
            ->assertJsonPath('data.categories.0.fields.0.key', 'loja')
            ->assertJsonPath('data.affiliations.0.name', 'Loja A')
            ->assertJsonPath('data.ticketTypes.0.name', 'Individual');
    }

    public function test_campo_obrigatorio_da_categoria_recusa(): void
    {
        $this->seminarEvent();

        $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['fields' => []]), // sem "loja"
        ]))->assertUnprocessable()->assertJsonValidationErrors(['items.0.fields.loja']);
    }

    public function test_email_de_participante_obrigatorio_com_multiplos(): void
    {
        $this->seminarEvent();

        $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(),
            $this->item(['participant_email' => null]),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['items.1.participant_email']);
    }

    public function test_evento_fora_de_venda_recusa(): void
    {
        $this->seminarEvent();
        $this->event->update(['status_id' => EventStatus::idFor(EventStatus::DRAFT)]);

        $this->postJson('/api/public/orders', $this->guestPayload([$this->item()]))
            ->assertStatus(409);
    }

    public function test_comprador_existente_e_vinculado_sem_duplicar(): void
    {
        $this->seminarEvent();
        User::factory()->create(['email' => 'comprador@ex.com', 'name' => 'Já Existe']);

        $this->postJson('/api/public/orders', $this->guestPayload([$this->item()]))->assertCreated();

        $this->assertSame(1, User::query()->where('email', 'comprador@ex.com')->count());
    }
}

<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/** US5 — acesso passwordless + escopo (comprador todos / participante o seu). */
class MagicLinkAccessTest extends CheckoutTestCase
{
    public function test_link_assinado_valido_redireciona(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute('auth.magic', now()->addDay(), ['user' => $user->id]);

        $this->get($url)->assertRedirect();
    }

    public function test_assinatura_invalida_403(): void
    {
        $user = User::factory()->create();

        $this->get("/auth/magic/{$user->id}")->assertForbidden();
    }

    public function test_comprador_ve_todos_e_participante_ve_o_seu(): void
    {
        $this->seminarEvent();

        $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['participant_name' => 'Irmão 1', 'participant_email' => 'i1@ex.com']),
            $this->item(['participant_name' => 'Irmão 2', 'participant_email' => 'i2@ex.com']),
        ]))->assertCreated();

        $buyer = User::query()->where('email', 'comprador@ex.com')->firstOrFail();
        $p1 = User::query()->where('email', 'i1@ex.com')->firstOrFail();

        // Comprador vê os 2 ingressos.
        $this->actingAs($buyer)->getJson('/api/tickets')->assertOk()->assertJsonCount(2, 'data');

        // Participante vê só o seu.
        $mine = $this->actingAs($p1)->getJson('/api/tickets')->assertOk()->json('data');
        $this->assertCount(1, $mine);
        $this->assertSame('Irmão 1', $mine[0]['participantName']);
    }

    public function test_solicitar_magic_link_e_neutro(): void
    {
        User::factory()->create(['email' => 'existe@ex.com']);

        $this->postJson('/api/auth/magic/request', ['email' => 'existe@ex.com'])
            ->assertOk()->assertJsonPath('data.sent', true);
        $this->postJson('/api/auth/magic/request', ['email' => 'naoexiste@ex.com'])
            ->assertOk()->assertJsonPath('data.sent', true);
    }
}

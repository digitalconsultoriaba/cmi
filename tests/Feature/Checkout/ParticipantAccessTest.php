<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\Role;
use App\Models\User;
use App\Notifications\AccessCreatedPtBr;
use Illuminate\Support\Facades\Notification;

/**
 * Acesso do participante (spec 015): na confirmação, cada e-mail do pedido sem
 * conta com senha ganha uma conta (papel attendee) + senha temporária + e-mail
 * de acesso. Contas já existentes não são tocadas.
 */
class ParticipantAccessTest extends CheckoutTestCase
{
    private function payOrder(array $items): string
    {
        $code = $this->postJson('/api/public/orders', $this->guestPayload($items))
            ->assertCreated()->json('data.order.code');

        $this->postJson("/api/public/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        return $code;
    }

    public function test_participante_novo_recebe_conta_com_senha_papel_e_email(): void
    {
        Notification::fake();
        $this->seminarEvent();

        $this->payOrder([
            $this->item(['participant_name' => 'Novo Participante', 'participant_email' => 'novo@ex.com']),
        ]);

        $part = User::query()->where('email', 'novo@ex.com')->firstOrFail();
        $this->assertNotNull($part->password);                 // conta com senha
        $this->assertTrue($part->hasRole(Role::ATTENDEE));      // papel participante
        Notification::assertSentTo($part, AccessCreatedPtBr::class);
    }

    public function test_email_com_conta_existente_nao_e_sobrescrito_nem_reenviado(): void
    {
        Notification::fake();
        $this->seminarEvent();

        // Conta pré-existente (com senha) — não deve ser tocada.
        $existing = User::factory()->create(['name' => 'Já Existe', 'email' => 'ja@ex.com']);
        $originalHash = $existing->password;

        $this->payOrder([
            $this->item(['participant_name' => 'Já Existe', 'participant_email' => 'ja@ex.com']),
        ]);

        $existing->refresh();
        $this->assertSame($originalHash, $existing->password);   // senha intacta
        Notification::assertNotSentTo($existing, AccessCreatedPtBr::class);
    }
}

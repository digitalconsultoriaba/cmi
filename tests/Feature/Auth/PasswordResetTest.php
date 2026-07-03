<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordPtBr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * US4 — redefinição de senha via broker (FR-011).
 */
class PasswordResetTest extends AuthTestCase
{
    use RefreshDatabase;

    public function test_solicitacao_tem_resposta_identica_exista_ou_nao_a_conta(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@exemplo.com']);

        $existe = $this->postJson('/api/auth/forgot-password', ['email' => 'ana@exemplo.com']);
        $naoExiste = $this->postJson('/api/auth/forgot-password', ['email' => 'ninguem@exemplo.com']);

        $existe->assertOk();
        $naoExiste->assertOk();
        $this->assertSame($existe->json(), $naoExiste->json(), 'não revela se a conta existe');

        Notification::assertSentTo($user, ResetPasswordPtBr::class);
    }

    public function test_fluxo_completo_troca_a_senha_e_permite_login(): void
    {
        $user = User::factory()->create(['email' => 'ana@exemplo.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'ana@exemplo.com',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'ana@exemplo.com',
            'password' => 'nova-senha-123',
        ])->assertOk();
    }

    public function test_token_reusado_e_recusado(): void
    {
        $user = User::factory()->create(['email' => 'ana@exemplo.com']);
        $token = Password::createToken($user);
        $payload = [
            'token' => $token,
            'email' => 'ana@exemplo.com',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ];

        $this->postJson('/api/auth/reset-password', $payload)->assertOk();
        $this->postJson('/api/auth/reset-password', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_token_expirado_e_recusado(): void
    {
        $user = User::factory()->create(['email' => 'ana@exemplo.com']);
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'ana@exemplo.com',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertUnprocessable();
    }

    public function test_emissao_nova_invalida_token_anterior(): void
    {
        $user = User::factory()->create(['email' => 'ana@exemplo.com']);
        $antigo = Password::createToken($user);
        Password::createToken($user); // emissão mais recente

        $this->postJson('/api/auth/reset-password', [
            'token' => $antigo,
            'email' => 'ana@exemplo.com',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertUnprocessable();
    }

    public function test_conta_so_google_ganha_senha_local(): void
    {
        $user = User::factory()->create([
            'email' => 'google@exemplo.com',
            'password' => null,
            'google_id' => 'g-123',
        ]);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'google@exemplo.com',
            'password' => 'agora-tenho-senha1',
            'password_confirmation' => 'agora-tenho-senha1',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->password);

        $this->postJson('/api/auth/login', [
            'email' => 'google@exemplo.com',
            'password' => 'agora-tenho-senha1',
        ])->assertOk()->assertJsonPath('data.hasGoogle', true);
    }

    public function test_solicitacoes_em_excesso_sao_bloqueadas(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'ana@exemplo.com']);

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'ana@exemplo.com'])->assertOk();
        }

        $this->postJson('/api/auth/forgot-password', ['email' => 'ana@exemplo.com'])
            ->assertStatus(429)
            ->assertJsonPath('type', 'throttled');
    }
}

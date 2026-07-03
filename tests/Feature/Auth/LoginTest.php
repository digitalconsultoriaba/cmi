<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US2 — login com proteção contra força bruta (FR-005/FR-006/FR-010).
 */
class LoginTest extends AuthTestCase
{
    use RefreshDatabase;

    public function test_login_com_credenciais_validas_estabelece_sessao(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@exemplo.com')
            ->assertJsonPath('data.hasPassword', true);

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_com_maiusculas_e_espacos_loga_normalmente(): void
    {
        User::factory()->create([
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => '  ANA@Exemplo.COM ',
            'password' => 'senha-forte-123',
        ])->assertOk();
    }

    public function test_credencial_errada_recebe_mensagem_generica_identica(): void
    {
        User::factory()->create([
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
        ]);

        $senhaErrada = $this->postJson('/api/auth/login', [
            'email' => 'ana@exemplo.com',
            'password' => 'errada-total',
        ])->assertUnprocessable();

        $emailInexistente = $this->postJson('/api/auth/login', [
            'email' => 'ninguem@exemplo.com',
            'password' => 'qualquer-coisa',
        ])->assertUnprocessable();

        // Mesma mensagem nos dois casos — não revela qual campo errou
        $this->assertSame(
            $senhaErrada->json('errors.email.0'),
            $emailInexistente->json('errors.email.0')
        );
        $this->assertGuest();
    }

    public function test_conta_so_google_recebe_orientacao_no_login_com_senha(): void
    {
        User::factory()->create([
            'email' => 'google@exemplo.com',
            'password' => null,
            'google_id' => 'g-123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'google@exemplo.com',
            'password' => 'tanto-faz-123',
        ])->assertUnprocessable();

        $this->assertStringContainsString('Google', $response->json('errors.email.0'));
    }

    public function test_forca_bruta_e_bloqueada_apos_5_tentativas(): void
    {
        User::factory()->create([
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
        ]);

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/auth/login', [
                'email' => 'ana@exemplo.com',
                'password' => 'errada-'.$i,
            ])->assertUnprocessable();
        }

        // 6ª tentativa (mesmo com a senha certa) → bloqueio temporário
        $this->postJson('/api/auth/login', [
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
        ])
            ->assertStatus(429)
            ->assertJsonPath('type', 'throttled');
    }

    public function test_sucesso_zera_o_limitador(): void
    {
        User::factory()->create([
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
        ]);

        foreach (range(1, 4) as $i) {
            $this->postJson('/api/auth/login', [
                'email' => 'ana@exemplo.com',
                'password' => 'errada-'.$i,
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
        ])->assertOk();

        // Limitador zerado: nova falha não bloqueia
        $this->postJson('/api/auth/login', [
            'email' => 'ana@exemplo.com',
            'password' => 'errada-de-novo',
        ])->assertUnprocessable();
    }
}

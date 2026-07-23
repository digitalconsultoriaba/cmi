<?php

namespace Tests\Feature\Auth;

use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * US1 — cadastro com e-mail e senha (contracts/auth-api.md).
 */
class RegisterTest extends AuthTestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Silva',
            'email' => 'ana@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ], $overrides);
    }

    public function test_cadastro_cria_conta_com_papel_attendee_e_sessao(): void
    {
        $response = $this->postJson('/api/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ana@exemplo.com')
            ->assertJsonPath('data.hasPassword', true)
            ->assertJsonPath('data.roles.0', Role::ATTENDEE);

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'ana@exemplo.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::ATTENDEE));
    }

    public function test_email_e_normalizado_no_cadastro(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload([
            'email' => '  ANA@Exemplo.COM ',
        ]))->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ana@exemplo.com']);
    }

    public function test_email_duplicado_mesmo_com_caixa_diferente_e_recusado(): void
    {
        User::factory()->create(['email' => 'ana@exemplo.com']);

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'ANA@EXEMPLO.COM',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_senha_curta_e_email_malformado_sao_recusados(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'nao-e-email',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

/**
 * US3 — as três vias do callback Google, com Socialite mockado
 * (research, Decisão 5; SC-006).
 */
class GoogleTest extends AuthTestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(array $attrs = []): void
    {
        $socialiteUser = (new SocialiteUser)->map(array_merge([
            'id' => 'g-12345',
            'name' => 'Ana do Google',
            'email' => 'ana@gmail.com',
            'avatar' => 'https://lh3.example.com/foto.jpg',
        ], $attrs));

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_devolve_url_de_autorizacao_no_envelope(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect->getTargetUrl')
            ->andReturn('https://accounts.google.com/o/oauth2/auth?client_id=x');
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->getJson('/api/auth/google/redirect')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://accounts.google.com/o/oauth2/auth?client_id=x');
    }

    public function test_via_3_email_novo_cria_conta_verificada_sem_senha_com_papel_attendee(): void
    {
        $this->mockGoogleUser();

        $this->get('/api/auth/google/callback')
            ->assertRedirect(config('app.frontend_url').'/entrar?google=ok');

        $user = User::query()->where('email', 'ana@gmail.com')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('g-12345', $user->google_id);
        $this->assertSame('https://lh3.example.com/foto.jpg', $user->avatar_url);
        $this->assertTrue($user->hasRole(Role::ATTENDEE));
        $this->assertAuthenticatedAs($user);
    }

    public function test_via_2_email_existente_vincula_sem_duplicar_nem_tocar_senha(): void
    {
        $existing = User::factory()->create([
            'email' => 'ana@gmail.com',
            'password' => 'senha-forte-123',
        ]);
        $passwordHash = $existing->fresh()->getAuthPassword();

        // E-mail chega do Google com caixa diferente — merge normalizado
        $this->mockGoogleUser(['email' => 'ANA@GMAIL.COM']);

        $this->get('/api/auth/google/callback')
            ->assertRedirect(config('app.frontend_url').'/entrar?google=ok');

        $this->assertSame(1, User::query()->where('email', 'ana@gmail.com')->count());

        $fresh = $existing->fresh();
        $this->assertSame('g-12345', $fresh->google_id);
        $this->assertSame($passwordHash, $fresh->getAuthPassword(), 'senha intocada');
        $this->assertAuthenticatedAs($existing);
    }

    public function test_via_1_google_id_ja_vinculado_loga_mesmo_com_email_mudado_no_google(): void
    {
        $user = User::factory()->create([
            'email' => 'antigo@gmail.com',
            'google_id' => 'g-12345',
        ]);

        $this->mockGoogleUser(['email' => 'novo-email@gmail.com']);

        $this->get('/api/auth/google/callback')
            ->assertRedirect(config('app.frontend_url').'/entrar?google=ok');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, User::query()->where('email', 'novo-email@gmail.com')->count());
    }

    public function test_erro_no_provedor_redireciona_com_flag_sem_sessao(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('provider error'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/auth/google/callback')
            ->assertRedirect(config('app.frontend_url').'/entrar?google=erro');

        $this->assertGuest();
    }
}

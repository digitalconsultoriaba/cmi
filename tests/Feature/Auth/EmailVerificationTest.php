<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailPtBr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * US1 — verificação de e-mail por link assinado (FR-003/FR-004).
 */
class EmailVerificationTest extends AuthTestCase
{
    use RefreshDatabase;

    private function signedUrlFor(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->email),
        ]);
    }

    public function test_link_assinado_verifica_e_redireciona_ao_front(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->signedUrlFor($user))
            ->assertRedirect(config('app.frontend_url').'/entrar?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_revisita_do_link_e_inocua(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->signedUrlFor($user);

        $this->get($url)->assertRedirect();
        $verifiedAt = $user->fresh()->email_verified_at;

        $this->get($url)->assertRedirect(config('app.frontend_url').'/entrar?verified=1');
        $this->assertEquals($verifiedAt, $user->fresh()->email_verified_at);
    }

    public function test_assinatura_adulterada_e_recusada(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->signedUrlFor($user);

        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_hash_de_outro_email_e_recusado(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->signedUrlFor($user, sha1('outro@exemplo.com')))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_reenvio_envia_notificacao_e_tem_limite_de_frequencia(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson('/api/auth/email/resend')->assertOk();
        Notification::assertSentTo($user, VerifyEmailPtBr::class);

        // Segundo reenvio em menos de 60s → 429 (throttle auth-email)
        $this->actingAs($user)->postJson('/api/auth/email/resend')
            ->assertStatus(429)
            ->assertJsonPath('type', 'throttled');
    }

    public function test_reenvio_com_conta_verificada_e_inocuo(): void
    {
        Notification::fake();
        $user = User::factory()->create(); // verificado por padrão

        $this->actingAs($user)->postJson('/api/auth/email/resend')->assertOk();
        Notification::assertNothingSent();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Spec 009 — autoatendimento da conta: dados, senha e foto.
 */
class ProfileTest extends AuthTestCase
{
    use RefreshDatabase;

    public function test_atualiza_dados_do_perfil(): void
    {
        $user = User::factory()->create(['name' => 'Antigo']);

        $this->actingAs($user)->putJson('/api/auth/profile', [
            'name' => 'Novo Nome', 'phone' => '(27) 99999-0000', 'document' => '123.456.789-00',
        ])->assertOk()->assertJsonPath('data.name', 'Novo Nome')
            ->assertJsonPath('data.phone', '(27) 99999-0000');
    }

    public function test_troca_senha_exige_a_atual_correta(): void
    {
        $user = User::factory()->create(['password' => 'senhaAtual1']);

        // Senha atual errada → 422
        $this->actingAs($user)->postJson('/api/auth/password', [
            'current_password' => 'errada', 'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertStatus(422);

        // Correta → troca
        $this->actingAs($user)->postJson('/api/auth/password', [
            'current_password' => 'senhaAtual1', 'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertOk();

        $this->assertTrue(Hash::check('novaSenha123', $user->fresh()->password));
    }

    public function test_upload_de_foto(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('foto.png', 200, 200),
        ])->assertOk()->assertJsonPath('data.avatarUrl', fn ($url) => $url !== null);

        $this->assertNotNull($user->fresh()->avatar_url);
    }

    public function test_perfil_exige_login(): void
    {
        $this->putJson('/api/auth/profile', ['name' => 'X'])->assertStatus(401);
        $this->postJson('/api/auth/password', ['password' => 'x'])->assertStatus(401);
    }
}

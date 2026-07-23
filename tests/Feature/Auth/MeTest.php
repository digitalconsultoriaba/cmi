<?php

namespace Tests\Feature\Auth;

use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US2 — consulta da própria conta e logout (contracts/auth-api.md).
 */
class MeTest extends AuthTestCase
{
    use RefreshDatabase;

    public function test_me_retorna_shape_completo_do_contrato(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@exemplo.com',
        ]);
        $user->assignRole(Role::ATTENDEE);
        $user->assignRole(Role::ADMIN);

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'name', 'email', 'emailVerified', 'document', 'phone',
                'avatarUrl', 'hasPassword', 'mustChangePassword', 'roles',
            ]])
            ->assertJsonPath('data.emailVerified', true)
            ->assertJsonPath('data.mustChangePassword', false)
            ->assertJsonCount(2, 'data.roles');
    }

    public function test_me_sem_sessao_responde_401_na_shape_padrao(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('type', 'unauthenticated')
            ->assertJsonStructure(['message', 'type', 'status']);
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/logout')->assertOk();

        $this->assertGuest('web');
    }

    public function test_logout_sem_sessao_responde_401(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }
}

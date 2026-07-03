<?php

namespace Tests\Feature\Foundation;

use App\Domain\Events\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * US4 — cenários de contracts/rbac.md.
 */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('require.role:admin')
            ->get('/api/_test/admin', fn () => ApiResponse::data(['ok' => true]));

        Route::middleware('require.role:admin,treasury')
            ->get('/api/_test/financeiro', fn () => ApiResponse::data(['ok' => true]));
    }

    private function userWithRoles(string ...$slugs): User
    {
        $user = User::factory()->create();
        $user->roles()->sync(Role::idsFor($slugs));

        return $user;
    }

    public function test_anonimo_recebe_401(): void
    {
        $this->getJson('/api/_test/admin')
            ->assertStatus(401)
            ->assertJson(['type' => 'unauthenticated', 'status' => 401]);
    }

    public function test_autenticado_sem_papel_recebe_403_sem_vazar_papeis_exigidos(): void
    {
        $attendee = $this->userWithRoles(Role::ATTENDEE);

        $response = $this->actingAs($attendee)->getJson('/api/_test/admin');

        $response->assertStatus(403)
            ->assertJson(['type' => 'forbidden', 'status' => 403])
            ->assertJsonStructure(['message', 'type', 'status']);

        $this->assertStringNotContainsString('admin', $response->json('message'));
    }

    public function test_papel_exigido_da_acesso(): void
    {
        $admin = $this->userWithRoles(Role::ADMIN);

        $this->actingAs($admin)->getJson('/api/_test/admin')->assertOk();
    }

    public function test_acumulo_de_papeis_da_acesso(): void
    {
        $multi = $this->userWithRoles(Role::ATTENDEE, Role::ADMIN);

        $this->actingAs($multi)->getJson('/api/_test/admin')->assertOk();
    }

    public function test_lista_de_papeis_aceita_qualquer_um(): void
    {
        $treasury = $this->userWithRoles(Role::TREASURY);
        $gate = $this->userWithRoles(Role::GATE);

        $this->actingAs($treasury)->getJson('/api/_test/financeiro')->assertOk();
        $this->actingAs($gate)->getJson('/api/_test/financeiro')->assertStatus(403);
    }
}

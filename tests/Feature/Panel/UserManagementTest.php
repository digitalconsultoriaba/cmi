<?php

namespace Tests\Feature\Panel;

use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * Spec 009 — gestão de usuários da equipe: admin cria financeiro/recepção com
 * e-mail + senha na hora.
 */
class UserManagementTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    public function test_admin_cria_usuario_financeiro_com_senha(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'Fulano Financeiro',
            'email' => 'financeiro@evento.local',
            'password' => 'senha12345',
            'role' => Role::TREASURY,
        ])->assertCreated();

        $response->assertJsonPath('data.email', 'financeiro@evento.local')
            ->assertJsonPath('data.roles', [Role::TREASURY]);

        $created = User::query()->where('email', 'financeiro@evento.local')->firstOrFail();
        $this->assertTrue(Hash::check('senha12345', $created->password), 'senha hasheada');
        $this->assertTrue($created->hasRole(Role::TREASURY));
    }

    public function test_listagem_traz_so_equipe_e_permite_trocar_papel(): void
    {
        $admin = $this->admin();

        $gate = User::factory()->create(['name' => 'Recepção Um']);
        $gate->assignRole(Role::GATE);
        $this->buyer(); // inscrito comum (attendee) NÃO deve aparecer

        $list = $this->actingAs($admin)->getJson('/api/admin/users')->assertOk();
        $emails = collect($list->json('data'))->pluck('email');
        $this->assertTrue($emails->contains($gate->email));
        $this->assertTrue($emails->contains($admin->email));

        // Promove recepção → financeiro
        $this->actingAs($admin)->putJson("/api/admin/users/{$gate->id}", [
            'role' => Role::TREASURY,
        ])->assertOk()->assertJsonPath('data.roles', [Role::TREASURY]);
        $this->assertTrue($gate->fresh()->hasRole(Role::TREASURY));
        $this->assertFalse($gate->fresh()->hasRole(Role::GATE));
    }

    public function test_email_duplicado_e_papel_invalido_recusam(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'X', 'email' => $admin->email, 'password' => 'senha12345', 'role' => Role::GATE,
        ])->assertUnprocessable();

        $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'Y', 'email' => 'novo@x.com', 'password' => 'senha12345', 'role' => 'attendee',
        ])->assertUnprocessable(); // attendee não é papel de equipe
    }

    public function test_nao_remove_a_propria_conta_e_rbac(): void
    {
        // Anônimo primeiro
        $this->getJson('/api/admin/users')->assertStatus(401);

        $admin = $this->admin();
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$admin->id}")->assertStatus(409);

        // Só admin gerencia usuários
        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);
        $this->actingAs($treasury)->getJson('/api/admin/users')->assertStatus(403);
    }
}

<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\Role;
use App\Models\User;

/** US4 — config admin de categorias/campos/afiliações. */
class ParticipantAdminConfigTest extends CheckoutTestCase
{
    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::ADMIN);

        return $u;
    }

    public function test_crud_de_categoria_campo_e_afiliacao(): void
    {
        $this->seminarEvent();
        $admin = $this->admin();
        $base = "/api/admin/events/{$this->event->id}";

        $catId = $this->actingAs($admin)->postJson("$base/participant-categories", [
            'key' => 'novato', 'label' => 'Nova categoria',
        ])->assertCreated()->json('data.id');

        $fieldId = $this->actingAs($admin)->postJson("$base/participant-categories/$catId/fields", [
            'key' => 'cargo', 'label' => 'Cargo', 'type' => 'conditional', 'required' => false,
            'config' => ['question' => 'Possui cargo?'],
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin)->putJson("$base/participant-categories/$catId/fields/$fieldId", [
            'key' => 'cargo', 'label' => 'Cargo atual', 'type' => 'text',
        ])->assertOk()->assertJsonPath('data.label', 'Cargo atual');

        $this->actingAs($admin)->postJson("$base/affiliations", ['name' => 'Loja Z'])
            ->assertCreated()->assertJsonPath('data.name', 'Loja Z');

        $this->actingAs($admin)->postJson("$base/affiliations/import", ['names' => "Loja X\nLoja Y\nLoja Z"])
            ->assertOk()->assertJsonPath('data.imported', 3);

        $this->actingAs($admin)->deleteJson("$base/participant-categories/$catId/fields/$fieldId")->assertOk();
        $this->assertSoftDeleted('participant_fields', ['id' => $fieldId]);
    }

    public function test_gate_nao_configura(): void
    {
        $this->seminarEvent();
        $gate = User::factory()->create();
        $gate->assignRole(Role::GATE);

        $this->actingAs($gate)->getJson("/api/admin/events/{$this->event->id}/participant-categories")
            ->assertForbidden();
    }

    public function test_tipo_de_campo_invalido_recusa(): void
    {
        $this->seminarEvent();
        $admin = $this->admin();
        $base = "/api/admin/events/{$this->event->id}";

        $catId = $this->actingAs($admin)->postJson("$base/participant-categories", [
            'key' => 'x', 'label' => 'X',
        ])->json('data.id');

        $this->actingAs($admin)->postJson("$base/participant-categories/$catId/fields", [
            'key' => 'y', 'label' => 'Y', 'type' => 'inexistente',
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }
}

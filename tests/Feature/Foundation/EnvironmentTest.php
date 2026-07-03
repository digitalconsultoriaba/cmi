<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US1 — ambiente e convenções de API (contracts/api-conventions.md).
 */
class EnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_responde_no_envelope_padrao(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['data' => ['status' => 'ok']]);
    }

    public function test_rota_inexistente_responde_404_na_shape_de_erro(): void
    {
        $this->getJson('/api/rota-que-nao-existe')
            ->assertNotFound()
            ->assertJson(['type' => 'not_found', 'status' => 404])
            ->assertJsonStructure(['message', 'type', 'status']);
    }
}

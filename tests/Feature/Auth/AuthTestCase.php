<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

abstract class AuthTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Simula requisição vinda do SPA (domínio stateful do Sanctum) para a
        // sessão por cookie funcionar como em produção.
        $this->withHeader('Referer', 'http://localhost:5173');
    }
}

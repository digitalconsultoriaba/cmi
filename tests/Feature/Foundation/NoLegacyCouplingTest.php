<?php

namespace Tests\Feature\Foundation;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * US2 / SC-004 — regressão permanente: nenhum conceito do sistema de origem
 * (maçonaria) pode existir no código (constituição, princípio I).
 */
class NoLegacyCouplingTest extends TestCase
{
    public function test_codigo_nao_contem_conceitos_do_sistema_de_origem(): void
    {
        // Padrões montados por concatenação para este arquivo nunca se autoacusar.
        $patterns = [
            '/owner'.'_type/',
            '/owner'.'_lodge/',
            '/Event'.'AccessGuard/',
            '/require\.'.'module/',
            '/seat_limit'.'_per_lodge/',
            '/\b'.'Lodge\b/',
            '/\b'.'Member\b/',
        ];

        $finder = (new Finder())
            ->files()
            ->in([
                base_path('app'),
                base_path('database'),
                base_path('routes'),
                base_path('frontend/src'),
            ])
            ->name(['*.php', '*.js', '*.jsx']);

        $violations = [];

        foreach ($finder as $file) {
            $contents = $file->getContents();

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents)) {
                    $violations[] = $file->getRelativePathname()." → $pattern";
                }
            }
        }

        $this->assertSame([], $violations, 'Acoplamento com o sistema de origem encontrado.');
    }
}

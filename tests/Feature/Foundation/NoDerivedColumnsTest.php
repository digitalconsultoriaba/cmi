<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * US3 — verificação estrutural: estado operacional é derivado, nunca coluna
 * (constituição, princípio II). `sold_count` é cache recalculável permitido.
 */
class NoDerivedColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_nao_contem_colunas_de_estado_derivado(): void
    {
        $forbidden = ['sales_open', 'available', 'sold_out', 'is_sold_out', 'is_current'];
        $tables = [
            'events', 'ticket_types', 'ticket_lots',
            'event_shirt_models', 'event_shirt_sizes', 'orders', 'tickets',
        ];

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);

            foreach ($forbidden as $column) {
                $this->assertNotContains(
                    $column,
                    $columns,
                    "Tabela '$table' não pode ter coluna de estado derivado '$column'."
                );
            }
        }
    }
}

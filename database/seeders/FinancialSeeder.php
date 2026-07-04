<?php

namespace Database\Seeders;

use App\Domain\Events\Models\FinancialCategory;
use App\Domain\Events\Models\FinancialPaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Estrutural (spec 010): formas de pagamento (lookup) e um conjunto inicial de
 * categorias de receita/despesa. Idempotente — seguro em qualquer ambiente.
 */
class FinancialSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            'pix' => 'Pix', 'credit_card' => 'Cartão de crédito',
            'debit_card' => 'Cartão de débito', 'boleto' => 'Boleto',
            'cash' => 'Dinheiro', 'transfer' => 'Transferência bancária',
            'deposit' => 'Depósito', 'barter' => 'Permuta',
            'courtesy' => 'Cortesia', 'other' => 'Outro',
        ];
        $sort = 0;
        foreach ($methods as $slug => $name) {
            FinancialPaymentMethod::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'sort' => $sort++, 'is_active' => true]
            );
        }

        $income = ['Ingressos', 'Inscrições', 'Patrocínios', 'Apoios',
            'Venda de produtos', 'Receita administrativa', 'Outras receitas'];
        $expense = ['Buffet', 'Espaço', 'Palestrante', 'Hospedagem', 'Passagem',
            'Marketing', 'Material gráfico', 'Brindes', 'Camisas',
            'Som e iluminação', 'Fotografia', 'Filmagem', 'Equipe de apoio',
            'Taxas', 'Administrativo', 'Sistema', 'Contabilidade', 'Outros custos'];

        foreach ($income as $i => $name) {
            FinancialCategory::query()->firstOrCreate(
                ['direction' => FinancialCategory::INCOME, 'name' => $name],
                ['is_active' => true, 'sort' => $i]
            );
        }
        foreach ($expense as $i => $name) {
            FinancialCategory::query()->firstOrCreate(
                ['direction' => FinancialCategory::EXPENSE, 'name' => $name],
                ['is_active' => true, 'sort' => $i]
            );
        }
    }
}

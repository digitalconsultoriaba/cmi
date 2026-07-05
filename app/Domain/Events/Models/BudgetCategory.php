<?php

namespace App\Domain\Events\Models;

/**
 * Categorias padrão dos itens de custo do orçamento (spec 011, FR-007).
 * Usadas para validação e agrupamento/relatórios.
 */
class BudgetCategory
{
    public const ALL = [
        'Espaço', 'Hospedagem', 'Alimentação', 'Bebidas', 'Som e iluminação',
        'Infraestrutura', 'Gráfica', 'Comunicação', 'Marketing', 'Palestrantes',
        'Transporte', 'Logística', 'Brindes', 'Cerimonial', 'Equipe', 'Segurança',
        'Fotografia e filmagem', 'Taxas', 'Outros',
    ];
}

<?php

namespace App\Domain\Events\Exceptions;

use Exception;

/**
 * Regra de negócio violada → HTTP 409 na shape padrão de erro
 * (specs/001-fundacao/contracts/api-conventions.md).
 * `$errors` opcional carrega detalhes estruturados (ex.: missing[] do publish).
 */
class DomainRuleViolation extends Exception
{
    public function __construct(
        string $message = 'Esta ação viola uma regra de negócio.',
        public readonly string $type = 'domain_rule',
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }
}

<?php

namespace App\Domain\Events\Exceptions;

use Exception;

/**
 * Regra de negócio violada → HTTP 409 na shape padrão de erro
 * (specs/001-fundacao/contracts/api-conventions.md).
 */
class DomainRuleViolation extends Exception
{
    public function __construct(
        string $message = 'Esta ação viola uma regra de negócio.',
        public readonly string $type = 'domain_rule',
    ) {
        parent::__construct($message);
    }
}

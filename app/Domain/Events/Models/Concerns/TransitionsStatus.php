<?php

namespace App\Domain\Events\Models\Concerns;

use App\Domain\Events\Exceptions\DomainRuleViolation;

/**
 * Guarda de transição de situação (constituição, princípio V): situações
 * terminais rejeitam qualquer transição com DomainRuleViolation (→ 409).
 * O model define `public const STATUS_LOOKUP = XxxStatus::class;`.
 */
trait TransitionsStatus
{
    public function transitionTo(string $statusSlug): static
    {
        $lookup = static::STATUS_LOOKUP;
        $current = $this->status?->slug;

        if ($current !== null && in_array($current, $lookup::TERMINAL, true)) {
            throw new DomainRuleViolation(
                "Situação '$current' é terminal e não permite transição.",
                'terminal_status'
            );
        }

        if (! in_array($statusSlug, $lookup::ALL, true)) {
            throw new DomainRuleViolation("Situação '$statusSlug' inexistente.", 'invalid_status');
        }

        $this->forceFill(['status_id' => $lookup::idFor($statusSlug)])->save();
        $this->unsetRelation('status');

        return $this;
    }
}

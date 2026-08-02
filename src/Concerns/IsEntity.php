<?php

namespace Splitstack\Domainable\Concerns;

use Splitstack\Domainable\Contracts\EnforcesInvariants;
use Splitstack\Domainable\Exceptions\InvariantViolationException;
use Splitstack\Domainable\Support\EntityReflector;

/**
 * @phpstan-require-implements EnforcesInvariants
 */
trait IsEntity
{
    private bool $quarantined = false;

    private array $quarantineReasons = [];

    public function assertInvariants(): void
    {
        foreach (EntityReflector::scan($this)['invariants'] as $label => $method) {
            try {
                $this->{$method}()->assert($this);
            } catch (\DomainException|\LogicException $e) {
                throw new InvariantViolationException($label, $e->getMessage(), $e);
            }
        }
    }

    public function quarantine(string $reason): void
    {
        $this->quarantined = true;
        $this->quarantineReasons[] = $reason;
    }

    public function isQuarantined(): bool
    {
        return $this->quarantined;
    }
}

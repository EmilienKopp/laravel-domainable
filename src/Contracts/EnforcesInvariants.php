<?php

namespace Splitstack\Domainable\Contracts;

interface EnforcesInvariants
{
    /**
     * Run every invariant declared on the object, throwing on the first breach.
     */
    public function assertInvariants(): void;

    public function quarantine(string $reason): void;

    public function isQuarantined(): bool;
}

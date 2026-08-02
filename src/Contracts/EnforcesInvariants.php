<?php

namespace Splitstack\Domainable\Contracts;

interface EnforcesInvariants
{
    public function assertInvariants(): void;

    public function quarantine(string $reason): void;

    public function isQuarantined(): bool;
}

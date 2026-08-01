<?php

namespace Splitstack\Domainable\Contracts;

use Splitstack\Domainable\Entity;

interface ProvidesEntity extends EnforcesInvariants
{
    public function asEntity(): Entity;
}

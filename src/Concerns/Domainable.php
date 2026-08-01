<?php

namespace Splitstack\Domainable\Concerns;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Contracts\ProvidesEntity;
use Splitstack\Domainable\Entity;
use Splitstack\Domainable\Support\EntityReflector;

/**
 * Give an Eloquent model a domain-facing entity view.
 *
 * Pulls in IsEntity so the model also enforces its own invariants. Calling
 * asEntity() asserts invariants before handing back the entity.
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements ProvidesEntity
 */
trait Domainable
{
    use IsEntity;

    public function asEntity(): Entity
    {
        $meta = EntityReflector::scan($this);

        $entity = new Entity($this, $meta['domain']);
        $entity->assertInvariants();

        return $entity;
    }
}

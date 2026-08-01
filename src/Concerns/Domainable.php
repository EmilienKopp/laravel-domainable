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

    /**
     * @param  bool  $assertInvariants  when false, skip the invariant check on
     *                                   hydration. The escape hatch for loading
     *                                   an entity known to be in an invalid
     *                                   state (inspection, repair). Later domain
     *                                   calls still assert.
     */
    public function asEntity(bool $assertInvariants = true): Entity
    {
        $meta = EntityReflector::scan($this);

        $entity = new Entity($this, $meta['domain']);

        if ($assertInvariants) {
            $entity->assertInvariants();
        }

        return $entity;
    }
}

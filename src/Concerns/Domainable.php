<?php

namespace Splitstack\Domainable\Concerns;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Contracts\ProvidesEntity;
use Splitstack\Domainable\Entity;
use Splitstack\Domainable\Support\EntityReflector;

/**
 * Give an Eloquent model a domain-facing entity view.
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
     *                                  hydration.
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

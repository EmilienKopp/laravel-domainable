<?php

namespace Splitstack\Domainable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Attributes\Domain;
use Splitstack\Domainable\Concerns\Domainable;
use Splitstack\Domainable\Contracts\ProvidesEntity;

/**
 * A domainable model with NO invariants. Used by the benchmarks to isolate the
 * cost of the entity proxy dispatch from the cost of assertInvariants().
 *
 * Reuses the example_models table so it needs no extra schema.
 */
class ProxyOnlyModel extends Model implements ProvidesEntity
{
    use Domainable;

    protected $table = 'example_models';

    protected $fillable = ['name'];

    #[Domain]
    public function rename(string $name): void
    {
        $this->name = $name;
    }
}

<?php

namespace Splitstack\Domainable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Attributes\Domain;
use Splitstack\Domainable\Concerns\Domainable;
use Splitstack\Domainable\Contracts\ProvidesEntity;
use Splitstack\Domainable\Data\Invariant;
use Splitstack\Domainable\Enums\HydrationPolicy;

class ModelWithQuarantine extends Model implements ProvidesEntity
{
    use Domainable;

    protected $fillable = ['name'];

    #[Domain]
    public function rename(string $name): void
    {
        $this->name = $name;
    }

    #[Domain]
    public function relabel(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    protected function nameIsLongEnough(): Invariant
    {
        return Invariant::make(
            rule: fn () => strlen($this->name) > 5,
            default: 'Invalid name',
            message: 'Invariant failed',
            policy: HydrationPolicy::Quarantine
        );
    }

    public static function violating()
    {
        return new self(['name' => 'short']);
    }
}

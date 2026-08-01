<?php

namespace Splitstack\Domainable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Attributes\Domain;
use Splitstack\Domainable\Concerns\Domainable;
use Splitstack\Domainable\Contracts\ProvidesEntity;
use Splitstack\Domainable\Data\Invariant;
use Splitstack\Domainable\Enums\HydrationPolicy;

class ModelWithAutocorrect extends Model implements ProvidesEntity
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

    public function nameIsUcfirst(): Invariant
    {
        return Invariant::make(
            rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
            message: 'Name must start with a capital letter',
            default: ucfirst($this->name),
            touches: ['name'],
            policy: HydrationPolicy::AutoCorrect,
        );
    }
}

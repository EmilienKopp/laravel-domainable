<?php

namespace Splitstack\Domainable\Tests\Fixtures;

use Splitstack\Domainable\Concerns\IsEntity;
use Splitstack\Domainable\Contracts\EnforcesInvariants;
use Splitstack\Domainable\Data\Invariant;
use Splitstack\Domainable\Enums\HydrationPolicy;

/**
 * A non-Eloquent aggregate root that reuses the invariant API through IsEntity,
 * with no Entity projection. The single invariant holds when every touched
 * attribute is shorter than three characters. Named constructors pick the
 * HydrationPolicy under test.
 */
class CustomEntity implements EnforcesInvariants
{
    use IsEntity;

    /**
     * @param  list<string>  $touches
     */
    public function __construct(
        public string $name = 'too long',
        public string $description = 'also too long',
        private array $touches = ['name'],
        private HydrationPolicy $policy = HydrationPolicy::Strict,
    ) {}

    /**
     * @param  list<string>  $touches
     */
    public static function strict(array $touches = ['name']): self
    {
        return new self(touches: $touches);
    }

    public static function autoCorrect(): self
    {
        return new self(policy: HydrationPolicy::AutoCorrect);
    }

    public static function lenient(): self
    {
        return new self(policy: HydrationPolicy::Lenient);
    }

    public function rename(string $newName): void
    {
        $this->name = $newName;
        $this->assertInvariants();
    }

    public function valuesAreShortEnough(): Invariant
    {
        return Invariant::make(
            touches: $this->touches,
            rule: fn ($value) => strlen((string) $value) < 3,
            default: '?',
            message: 'Invariant failed',
            policy: $this->policy,
        );
    }
}

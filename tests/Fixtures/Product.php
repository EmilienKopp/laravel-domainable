<?php

namespace Splitstack\Domainable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Attributes\Domain;
use Splitstack\Domainable\Concerns\Domainable;
use Splitstack\Domainable\Contracts\ProvidesEntity;
use Splitstack\Domainable\Data\Invariant;

class Product extends Model implements ProvidesEntity
{
    use Domainable;

    protected $fillable = ['name', 'price'];

    protected $casts = [
        'price' => 'integer',
        'active' => 'boolean',
    ];

    #[Domain]
    public function reprice(int $price, bool $notify = true): void
    {
        $this->price = $price;
    }

    #[Domain]
    public function activate(): static
    {
        $this->active = true;

        return $this;
    }

    protected function priceIsNonNegative(): Invariant
    {
        return Invariant::make(
            rule: fn () => $this->price >= 0,
            default: 0,
            message: 'Invariant failed'
        );
    }
}

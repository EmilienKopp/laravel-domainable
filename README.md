# Laravel Entity Model

**Pragma > Dogma.**

This package slightly bends the reality of Dependency Inversion Principle,
to give a pragmatic edge to your codebase:

> Eloquent ain't going anywhere. A lot of domain-entity logic could live in there.

## The idea

|                       | Plain DDD in Laravel                   | With this package                            |
| --------------------- | -------------------------------------- | -------------------------------------------- |
| Classes per aggregate | Model plus a hand-written entity       | Just the model                               |
| Model ↔ entity        | Mapping code you write and maintain    | Runtime view, no mapping                     |
| Domain surface        | Whatever the entity happens to expose  | Additive, opt in per method with `#[Domain]` |
| Infrastructure leaks  | Likely (`save`, `newQuery`, relations) | Can't leak, never exposed                    |
| Invariants            | Called by hand, easy to forget         | Run automatically after every domain call    |

Of course, you can still write your own aggregate root and entity classes if you'd like.

This package is just a convenience for the common case of a single model per aggregate.

Feel free to use the `IsEntity` trait on your own Domain classes to open the same `Invariants` API.

## Requirements

- PHP 8.4+
- Laravel 10, 11, 12, or 13

## Installation

```bash
composer require splitstack/laravel-entity-model
```

The service provider is auto-discovered.

## Usage

Add the `Domainable` trait and the `ProvidesEntity` contract to a model. Mark
domain methods with `#[Domain]`. Declare invariants as methods that return an
`Invariant` value object.

```php
use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Attributes\Domain;
use Splitstack\Domainable\Data\Invariant;
use Splitstack\Domainable\Contracts\ProvidesEntity;
use Splitstack\Domainable\Concerns\Domainable;

class Order extends Model implements ProvidesEntity
{
    use Domainable;

    protected $fillable = ['status', 'total'];

    protected $casts = ['total' => 'integer'];

    #[Domain]
    public function cancel(): void
    {
        $this->status = 'cancelled';
    }

    #[Domain]
    public function applyDiscount(int $amount): static
    {
        $this->total -= $amount;

        return $this;
    }

    protected function totalIsNonNegative(): Invariant
    {
        return Invariant::make(
            rule: fn () => $this->total >= 0,
            message: 'total below zero',
        );
    }

    // Regular Eloquent behavior lives here too, untouched.
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
```

Declare a repository for the model. Its `find()` and `all()` return entities, not
models:

```php
use Splitstack\Domainable\Repository\BaseRepository;

class OrderRepository extends BaseRepository
{
    protected string $for = Order::class;
}
```

Then work through the entity it hands back:

```php
$orders = new OrderRepository();

$order = $orders->find($id); // an Entity, never the raw model

$order->total;             // read-only attribute access
$order->cancel();          // allowed: marked #[Domain]. Invariants run after.
$order->applyDiscount(50); // fluent: returns the entity, never the raw model

$order->save();            // BadMethodCallException: not domain behavior
$order->newQuery();        // BadMethodCallException: not domain behavior
```

Quarantined entities (see [Hydration policies](#hydration-policies)) are handled
by the repository:

```php
$orders->all();                          // excludes quarantined entities
$orders->withQuarantined();              // includes them
$orders->find($id);                      // returns the entity even if quarantined
$orders->find($id, nullIfQuarantined: true); // null if quarantined
```

### What the entity exposes

- **Attributes**, read-only, through `__get` (casts and accessors apply).
- **Methods marked `#[Domain]`**, forwarded to the model. Anything else throws
  `BadMethodCallException`.
- **`assertInvariants()`**, to run every invariant on demand (a repository can
  call it before persisting).
- **`isQuarantined()`**, true when a `Quarantine`-policy invariant failed.
- **`toModel()`**, to hand the backing model to the infrastructure layer.

A `#[Domain]` method that returns `$this` on the model gives you back the
entity, so fluency works without leaking the model.

### Invariants

An invariant method takes no arguments and returns an `Invariant` value object
built with `Invariant::make()`:

- `rule`: a closure returning `true` when the state is valid.
- `message`: the text surfaced when the rule fails.
- `touches`: optional attribute names to check the rule against. Without it, the
  rule takes no arguments and reads `$this` (`fn () => $this->total >= 0`). With
  it, the rule receives each named attribute's value and must hold for all of
  them (`fn ($value) => $value >= 0`).
- `policy`: a `HydrationPolicy`, default `Strict` (see below).
- `default`: replacement value used by the `AutoCorrect` policy.

Invariants run automatically after every domain operation. A broken one surfaces
as a `Splitstack\Domainable\Exceptions\InvariantViolationException` carrying the
method name as its label.

```php
$order->applyDiscount(999999);
// InvariantViolationException: Invariant [totalIsNonNegative] violated: total below zero
```

#### Hydration policies

`policy:` decides what a failed invariant does. `Lenient` and `AutoCorrect`
require `touches`; `AutoCorrect` also requires a `default`.

| Policy             | On failure                                                |
| ------------------ | --------------------------------------------------------- |
| `Strict` (default) | Throws `InvariantViolationException`.                     |
| `Quarantine`       | Flags the entity (`isQuarantined()`) instead of throwing. |
| `Lenient`          | Accepts the value, no throw.                              |
| `AutoCorrect`      | Writes `default` into the touched attributes.             |

```php
use Splitstack\Domainable\Enums\HydrationPolicy;

return Invariant::make(
    rule: fn ($value) => $value >= 0,
    message: 'total below zero',
    touches: ['total'],
    policy: HydrationPolicy::Quarantine,
);
```

The invariant API is split out of `Domainable` into its own `IsEntity` trait
(`Domainable` uses it). Attach `IsEntity` and the `EnforcesInvariants` contract to
any class, no Eloquent needed, to reuse invariants on a custom aggregate root and
call `assertInvariants()` yourself.

```php
use Splitstack\Domainable\Concerns\IsEntity;
use Splitstack\Domainable\Contracts\EnforcesInvariants;

class Cart implements EnforcesInvariants
{
    use IsEntity;

    // ... methods returning Invariant, checked by assertInvariants()
}
```

### Persisting

The repository is the hydrate direction. To persist, hand the model back to your
infrastructure layer with `toModel()`:

```php
$repository->save($order->toModel());
```

## Type safety

The entity is a magic-method proxy, so out of the box your editor and static
analyzer see a generic `Entity`. Two generators buy the type safety back. Both
read the same `#[Domain]` methods and attributes you already declared.

### Typed domain interface (strictest)

Generate an interface that lists the domain methods and attribute types. Have
the model implement it, so static analysis checks the methods really exist, and
type `asEntity()` to return it so consumers get full autocomplete.

```bash
php artisan entity:interface "App\Models\Order"           # print
php artisan entity:interface "App\Models\Order" --write   # write OrderEntity.php beside the model
```

```php
namespace App\Models;

/**
 * Domain entity contract for \App\Models\Order.
 *
 * @property-read int    $total
 * @property-read string $status
 */
interface OrderEntity
{
    public function cancel(): void;

    public function applyDiscount(int $amount): self;
}
```

Options: `--namespace=`, `--suffix=` (defaults to `Entity`), `--path=`,
`--write`.

### In-place model annotations (ide-helper style)

Add a marker-tagged docblock above the model with `@property-read` lines and a
typed `@method asEntity()`. No new type to wire. Re-running replaces the block
instead of duplicating it.

```bash
php artisan entity:annotations "App\Models\Order"                                    # print docblock
php artisan entity:annotations "App\Models\Order" --entity="App\Models\OrderEntity" --write
```

Pass `--entity=` to point `asEntity()` at a generated interface. Without
`--write` the docblock is printed so you can review it first.

## Read-side projections

`Splitstack\Domainable\EntityModel` is a separate base for the read side: an
Eloquent model you can query but never persist. It is not the domain entity. Use
it when you want a cheap, immutable result object that nobody should mutate or
save.

## Testing

Domain behavior needs no database. An Eloquent model works in memory, so build
one and exercise it directly:

```php
$order = Order::factory()->make(['total' => 100]); // or new Order([...])

$order->asEntity()->applyDiscount(50);

expect(fn () => Order::factory()->make(['total' => -1])->asEntity())
    ->toThrow(InvariantViolationException::class);

```

Only repository tests (find, all) need a real or in-memory sqlite database,
since they cross the persistence boundary.

## License

MIT

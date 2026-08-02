# Laravel Domainable

[![Tests](https://github.com/EmilienKopp/laravel-domainable/actions/workflows/tests.yml/badge.svg)](https://github.com/EmilienKopp/laravel-domainable/actions/workflows/tests.yml)
[![Coverage](https://raw.githubusercontent.com/EmilienKopp/laravel-domainable/main/.github/badges/coverage.svg)](https://github.com/EmilienKopp/laravel-domainable/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/splitstack/laravel-domainable.svg)](https://packagist.org/packages/splitstack/laravel-domainable)
[![Total Downloads](https://img.shields.io/packagist/dt/splitstack/laravel-domainable.svg)](https://packagist.org/packages/splitstack/laravel-domainable)
[![PHP Version](https://img.shields.io/packagist/php-v/splitstack/laravel-domainable.svg)](https://packagist.org/packages/splitstack/laravel-domainable)
[![License](https://img.shields.io/packagist/l/splitstack/laravel-domainable.svg)](https://github.com/EmilienKopp/laravel-domainable/blob/main/LICENSE)

**Pragma > Dogma.**

Adopt DDD principles in Laravel and Eloquent without the overhead of writing and
maintaining a separate entity class for every model.

This package slightly bends the reality of the Dependency Inversion Principle to
give a pragmatic edge to your codebase. You can still write your own aggregate
root and entity classes when you want to; this is just a convenience for the
common case of a single model per aggregate.

## What you get

|                       | Plain DDD in Laravel                   | With this package                            |
| --------------------- | -------------------------------------- | -------------------------------------------- |
| Classes per aggregate | Model plus a hand-written entity       | Just the model                               |
| Model ↔ entity        | Mapping code you write and maintain    | Runtime view, no mapping                     |
| Domain surface        | Whatever the entity happens to expose  | Additive, opt in per method with `#[Domain]` |
| Infrastructure leaks  | Likely (`save`, `newQuery`, relations) | Can't leak, never exposed                    |
| Invariants            | Called by hand, easy to forget         | Run automatically after every domain call    |

The core capabilities, in order:

- **Domainable** turns a model into a domain-facing **Entity**: read-only
  attributes plus the methods you opt into, nothing else.
- **Invariants** are rules that run automatically after every domain call, with
  policies that decide what a failure does (throw, quarantine, correct, ignore).
- **Repositories** hydrate entities out of the database and keep infrastructure
  on their side of the boundary.
- **Traits** (`Domainable`, `IsEntity`) let you reuse the same machinery on
  models or on plain custom aggregate roots.

## Requirements

- PHP 8.4+
- Laravel 10, 11, 12, or 13

## Disclaimer

This package is in early development (`v0.x`). The API is not stable yet, and breaking
changes may be made without warning.

## Installation

```bash
composer require splitstack/laravel-domainable
```

The service provider is auto-discovered.

## What a Domainable is

A **Domainable** is an Eloquent model that can hand back a domain **Entity**: a
runtime view of itself that exposes only what the domain is allowed to touch.

The Entity is a proxy over the model. It gives you:

- **Attributes**, read-only, through `__get` (casts and accessors apply).
- **Methods marked `#[Domain]`**, forwarded to the model. Anything else (`save`,
  `newQuery`, relations, arbitrary setters) throws `BadMethodCallException`, so
  infrastructure can't leak into domain code.

You make a model domainable by adding the `Domainable` trait and the
`ProvidesEntity` contract, marking the domain methods with `#[Domain]`, and
declaring any invariants (covered in the next section).

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

Call `asEntity()` to cross into the domain view:

```php
$order = Order::find($id)->asEntity(); // an Entity, never the raw model

$order->total;             // read-only attribute access
$order->cancel();          // allowed: marked #[Domain]. Invariants run after.
$order->applyDiscount(50); // fluent: returns the entity, never the raw model

$order->save();            // 💥 BadMethodCallException: not domain behavior
$order->newQuery();        // 💥 BadMethodCallException: not domain behavior
```

A `#[Domain]` method that returns `$this` on the model gives you back the
entity, so fluency works without ever leaking the model. Two more methods round
out the surface:

ℹ️ Nothing prevents you from calling `#[Domain]` methods on the model itself or
in repository/infra code, but we wouldn't recommend it. The point is that the
entity mutates itself and the persistence is dumb.

- **`assertInvariants()`** runs every invariant on demand.
- **`toModel()`** hands the backing model back to the infrastructure layer.

## Invariants

An **invariant** is a rule that must hold for the entity to be valid. Invariants
run automatically after every domain operation, and `asEntity()` asserts them
before handing the entity back, so an entity can never escape into your domain in
a state a strict invariant forbids.

An invariant is a method that takes no arguments and returns an `Invariant`
value object built with `Invariant::make()`:

- `rule`: a closure returning `true` when the state is valid.
- `message`: the text surfaced when `$rule` fails.
- `touches`: optional attribute names to check the rule against. Without it, the
  rule takes no arguments and reads `$this` (`fn () => $this->total >= 0`). With
  it, the rule receives each named attribute's value and must hold for all of
  them (`fn ($value) => $value >= 0`).
- `policy`: a `HydrationPolicy`, default `Strict` (see below).
- `default`: replacement value used by the `AutoCorrect` policy.

A broken invariant under the default policy surfaces as an
`InvariantViolationException` carrying the method name as its label:

```php
$order->applyDiscount(999999);
// InvariantViolationException: Invariant [totalIsNonNegative] violated: total below zero
```

### Hydration policies

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

The `Quarantine` policy is what makes an entity load in an invalid state without
throwing. `isQuarantined()` reports it, and the repository class decides how to
treat quarantined entities (see [below](#quarantined-entities)).

### Invariant props

#### `$rule`

The predicate closure that checks the invariant. It must return `true` when the
state is valid.

The closure can work in two ways:

- Don't use `$touches` (see below): the closure takes no arguments, you just thread the Entity's state through `$this`
- Use `$touches`: the closure receives each named attribute's value and must hold for all of them

#### `$touches`

Allows to apply the predicate callback to a certain set of attributes,
and to check the `$rule` against several variables.
Useful when the same rule applies to multiple attributes.

⚠️ It is required for `Lenient` and `AutoCorrect` policies.

#### `$default`

The replacement value used by the `AutoCorrect` policy.

#### Examples

```php
Invariant::make(
    rule: fn () => $this->total >= 0, // the entity's `total` attribute
    message: 'total below zero',
);

Invariant::make(
    rule: fn ($value) => $value >= 0, // the `total` and `subtotal` attribute's values
    message: 'totals below zero',
    touches: ['total'],
    default: 0,
    policy: HydrationPolicy::AutoCorrect, // the `total` attribute is corrected to 0 if the invariant fails
);

Invariant::make(
    touches: ['first_name','last_name'],
    rule: fn ($value) => strlen($value) >= 3, // the `first_name` and `last_name` attribute's values are checked
    message: 'names cannot be too short',
);
```

## Repositories

A **repository** is the hydrate direction: it pulls entities out of the database
so the rest of your domain code never sees a raw model. Its `find()` and `all()`
return entities, not models.

Declare one by naming the model it is for:

```php
use Splitstack\Domainable\Repository\BaseRepository;

class OrderRepository extends BaseRepository
{
    protected string $for = Order::class;
}
```

Then work through the entities it hands back:

```php
$orders = new OrderRepository();

$order = $orders->find($id); // an Entity, never the raw model
$order->cancel();

$all = $orders->all();       // a collection of entities
```

### Quarantined entities

A **quarantined entity** is one that hydrated in an invalid state (a
`Quarantine`-policy invariant failed) but that you still want to load, flag, and
handle rather than reject outright. The repository decides how to treat them:

```php
$orders->all();                              // excludes quarantined entities
$orders->withQuarantined();                  // includes them
$orders->find($id);                          // returns the entity even if quarantined (⚠️if your data is corrupted and does not meet the invariant, this will throw InvariantViolationException)
$orders->find($id, nullIfQuarantined: true); // null if quarantined
$orders->find($id, fetchUnsafe: true);       // returns the entity without checking invariants

$orderEntity->isQuarantined();                     // true when a Quarantine-policy invariant failed
```

`fetchUnsafe: true` is the escape hatch for loading an entity you already know might be
invalid, so you can inspect or repair it instead of having `find()` throw. It only
skips the check on hydration.

Any later `#[Domain]` call on that entity **still asserts**,
so a broken strict invariant surfaces the moment you try to act on it.
Unlike quarantine, it does not flag the entity (`isQuarantined()` stays `false`)
and it applies per fetch rather than changing an invariant's policy everywhere.

#### Saving entities

To persist changes, only call `save()` on the repository, not on the entity.

```php
$orderEntity->save();         // 💥 BadMethodCallException: not domain behavior
$orders->save($orderEntity);  // ✅ allowed: repository handles persistence
```

## Using the traits in custom entities

The invariant machinery is split out of `Domainable` into its own `IsEntity`
trait (which `Domainable` uses). Attach `IsEntity` and the `EnforcesInvariants`
contract to any class, no Eloquent needed, to reuse invariants on a custom
aggregate root and call `assertInvariants()` yourself.

```php
use Splitstack\Domainable\Concerns\IsEntity;
use Splitstack\Domainable\Contracts\EnforcesInvariants;

class MyAggregateRootOrEntity implements EnforcesInvariants
{
    use IsEntity;

    // ... methods returning Invariant, checked by assertInvariants()
}
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

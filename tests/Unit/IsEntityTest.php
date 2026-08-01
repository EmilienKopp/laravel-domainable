<?php

use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Entity;
use Splitstack\Domainable\Exceptions\InvariantViolationException;
use Splitstack\Domainable\Tests\Fixtures\CustomEntity;
use Splitstack\Domainable\Tests\Fixtures\ExampleModel;

test('asEntity() returns an additive Entity, not a model', function () {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();

    expect($entity)->toBeInstanceOf(Entity::class)
        ->and($entity)->not->toBeInstanceOf(Model::class);
});

test('entity exposes attributes read-only through __get', function () {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();

    expect($entity->name)->toBe('Long enough name')
        ->and(isset($entity->name))->toBeTrue();
});

test('entity forwards a #[Domain] method to the model', function () {
    $model = new ExampleModel(['name' => 'Long enough name']);
    $entity = $model->asEntity();

    $entity->rename('Renamed and long');

    expect($entity->name)->toBe('Renamed and long')
        ->and($model->name)->toBe('Renamed and long');
});

test('entity denies any method not marked #[Domain]', function (string $method, array $args) {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();
    $entity->{$method}(...$args);
})->throws(BadMethodCallException::class)->with([
    'save' => ['save', []],
    'delete' => ['delete', []],
    'newQuery' => ['newQuery', []],
]);

test('a fluent domain method returns the entity, never the raw model', function () {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();

    $result = $entity->relabel('Also long enough');

    expect($result)->toBe($entity)
        ->and($result)->toBeInstanceOf(Entity::class);
});

test('invariants run after a domain operation and block invalid state', function () {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();

    $entity->rename('short');
})->throws(InvariantViolationException::class, 'Invariant [nameIsLongEnough] violated: Invariant failed');

test('assertInvariants can be called directly before persisting', function () {
    $entity = (new ExampleModel(['name' => 'tiny']))->asEntity();

    $entity->assertInvariants();
})->throws(InvariantViolationException::class, 'nameIsLongEnough');

test('toModel hands the backing model to infrastructure', function () {
    $model = new ExampleModel(['name' => 'Long enough name']);

    expect($model->asEntity()->toModel())->toBe($model);
});

test('invariant assertion works on several properties through $touches param', function () {
    CustomEntity::strict(['name', 'description'])->assertInvariants();
})->throws(InvariantViolationException::class, 'Invariant [valuesAreShortEnough] violated: Invariant failed');

test('invariant assertion can be used on custom Domain class', function () {
    CustomEntity::strict()->assertInvariants();
})->throws(InvariantViolationException::class, 'Invariant [valuesAreShortEnough] violated: Invariant failed');

test('custom classes can use assertInvariants() explicitly', function () {
    $customEntity = CustomEntity::strict();

    $customEntity->rename('ok');
    expect($customEntity->name)->toBe('ok');
    expect(fn () => $customEntity->rename('waytoolong'))
        ->toThrow(InvariantViolationException::class, 'Invariant [valuesAreShortEnough] violated: Invariant failed');
});

test('default value is applied when invariant fails and policy is AutoCorrect', function () {
    $customEntity = CustomEntity::autoCorrect();

    $customEntity->assertInvariants();

    expect($customEntity->name)->toBe('?');
});

test('lenient policy allows ignoring invariants further down the calls', function () {
    $customEntity = CustomEntity::lenient();

    $customEntity->assertInvariants();

    expect($customEntity->name)->toBe('too long');
    $customEntity->rename('ok');
    expect($customEntity->name)->toBe('ok');
    $customEntity->rename('waytoolong');
    expect($customEntity->name)->toBe('waytoolong');
});

test('throws on calling save() on entity', function () {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();

    $entity->save();
})->throws(BadMethodCallException::class, 'Not domain behavior: save');

test('throws on calling delete() on entity', function () {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();

    $entity->delete();
})->throws(BadMethodCallException::class, 'Not domain behavior: delete');

test('throws on calling newQuery() on entity', function () {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();

    $entity->newQuery();
})->throws(BadMethodCallException::class, 'Not domain behavior: newQuery');

test('throws on calling update() on entity', function () {
    $entity = (new ExampleModel(['name' => 'Long enough name']))->asEntity();

    $entity->update(['name' => 'New name']);
})->throws(BadMethodCallException::class, 'Not domain behavior: update');

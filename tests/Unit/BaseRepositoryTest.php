<?php

namespace Splitstack\Domainable\Tests\Unit;

use Splitstack\Domainable\Entity;
use Splitstack\Domainable\Exceptions\InvariantViolationException;
use Splitstack\Domainable\Repository\BaseRepository;
use Splitstack\Domainable\Tests\Fixtures\ExampleModel;
use Splitstack\Domainable\Tests\Fixtures\ModelWithQuarantine;

test('BaseRepository can find an entity by ID', function () {
    $model = ExampleModel::create(['name' => 'LongEnoughName']); // Valid name length > 5
    $repository = new class extends BaseRepository
    {
        protected string $for = ExampleModel::class;
    };

    $entity = $repository->find($model->id);
    expect($entity)->not()->toBeNull();
    expect($entity->name)->toBe('LongEnoughName');
    expect($entity)->toBeInstanceOf(Entity::class);
});

test('BaseRepository returns null for non-existent entity', function () {
    $repository = new class extends BaseRepository
    {
        protected string $for = ExampleModel::class;
    };

    $entity = $repository->find(42);
    expect($entity)->toBeNull();
});

test('fetching corrupted data (invariants violated in DB) throws an exception', function () {
    $model = ExampleModel::create(['name' => 'Test']); // Violates the "nameIsLongEnough" invariant, which requires name length > 5

    $repository = new class extends BaseRepository
    {
        protected string $for = ExampleModel::class;
    };

    $repository->find($model->id);
})->throws(InvariantViolationException::class, 'Invariant [nameIsLongEnough] violated: Invariant failed');

test('fetchUnsafe loads an entity that violates a strict invariant instead of throwing', function () {
    $model = ExampleModel::create(['name' => 'Test']); // Violates the "nameIsLongEnough" invariant

    $repository = new class extends BaseRepository
    {
        protected string $for = ExampleModel::class;
    };

    $entity = $repository->find($model->id, fetchUnsafe: true);

    expect($entity)->toBeInstanceOf(Entity::class)
        ->and($entity->name)->toBe('Test');

    // The escape hatch only skips the hydration check; a later domain call still asserts.
    expect(fn () => $entity->rename('X'))->toThrow(InvariantViolationException::class);
});

test('Base repository quarantines entities that violate invariants', function () {
    ModelWithQuarantine::create(['name' => 'Test']); // corrupted data
    ModelWithQuarantine::create(['name' => 'ValidName']); // valid data
    $repository = new class extends BaseRepository
    {
        protected string $for = ModelWithQuarantine::class;
    };

    $entities = $repository->all();
    expect($entities)->toBeArray()
        ->and($entities)->toHaveCount(1); // One valid, one quarantined
});

test('it can save an entity', function () {
    $entity = ExampleModel::create(['name' => 'ValidName'])->asEntity();

    $rawInMemoryEntity = clone $entity;
    $rawInMemoryEntity->rename('NewName');

    $repository = new class extends BaseRepository
    {
        protected string $for = ExampleModel::class;
    };
    $repository->save($rawInMemoryEntity);

    $fetchedEntity = $repository->find($rawInMemoryEntity->id);

    expect($fetchedEntity)->not()->toBeNull()
        ->and($fetchedEntity->name)->toBe('NewName')
        ->and($fetchedEntity->id)->toBe($rawInMemoryEntity->id);
});

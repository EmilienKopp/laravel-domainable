<?php

namespace Splitstack\Domainable\Tests\Unit;

use Splitstack\Domainable\Data\Invariant;
use Splitstack\Domainable\Enums\HydrationPolicy;
use Splitstack\Domainable\Tests\Fixtures\ModelWithAutocorrect;
use Splitstack\Domainable\Tests\Fixtures\ModelWithQuarantine;

test('Invariant throws on AutoCorrect policy if no touched properties are provided', function () {
    Invariant::make(
        rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: ucfirst('lowercase'),
        touches: [],
        policy: HydrationPolicy::AutoCorrect,
    )->on(new ModelWithAutocorrect(['name' => 'lowercase']));
})->throws(\RuntimeException::class, 'AutoCorrect policy requires at least one touched property to correct.');

test('Invariant throws on AutoCorrect policy if no default value is provided', function () {
    Invariant::make(
        rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: null,
        touches: ['name'],
        policy: HydrationPolicy::AutoCorrect,
    )->on(new ModelWithAutocorrect(['name' => 'lowercase']));
})->throws(\RuntimeException::class, 'AutoCorrect policy requires a default value to correct to.');

test('Invariant throws on Lenient policy if no touched properties are provided', function () {
    Invariant::make(
        rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: ucfirst('lowercase'),
        touches: [],
        policy: HydrationPolicy::Lenient,
    )->on(new ModelWithAutocorrect(['name' => 'lowercase']));
})->throws(\RuntimeException::class, 'Lenient policy requires at least one touched property to allow ignoring.');

test('the on() method returns a new Invariant instance with the model set', function () {
    $invariant = Invariant::make(
        rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: ucfirst('lowercase'),
        touches: ['name'],
        policy: HydrationPolicy::AutoCorrect,
    );

    $model = new ModelWithAutocorrect(['name' => 'lowercase']);
    $newInvariant = $invariant->on($model);

    expect($newInvariant)->not()->toBe($invariant);
    expect($newInvariant->model)->toBe($model);
});

test('fromArray instantiates a new Invariant with the provided properties', function () {
    $data = [
        'rule' => fn ($value) => $value !== '' && ctype_upper($value[0]),
        'message' => 'Name must start with a capital letter',
        'default' => ucfirst('lowercase'),
        'touches' => ['name'],
        'policy' => HydrationPolicy::AutoCorrect,
    ];

    $invariant = Invariant::fromArray($data);

    expect($invariant->rule)->toBe($data['rule']);
    expect($invariant->message)->toBe($data['message']);
    expect($invariant->default)->toBe($data['default']);
    expect($invariant->touches)->toBe($data['touches']);
    expect($invariant->policy)->toBe($data['policy']);
});

test('assert() throws if no subject is provided when touches is not empty', function () {
    $invariant = Invariant::make(
        rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: ucfirst('lowercase'),
        touches: ['name'],
        policy: HydrationPolicy::AutoCorrect,
    );

    $invariant->assert();
})->throws(\RuntimeException::class);

test('assert() does not throw if no subject is provided when touches is empty', function () {
    $value = 'Lowercase';
    $invariant = Invariant::make(
        rule: fn () => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: ucfirst('lowercase'),
        touches: [],
        policy: HydrationPolicy::Strict,
    );

    $invariant->assert();
    expect(true)->toBeTrue(); // If no exception is thrown, the test passes
});

test('ignored properties are skipped during invariant assertion', function () {
    $invariant = Invariant::make(
        rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: ucfirst('lowercase'),
        touches: ['name', 'description'],
        policy: HydrationPolicy::AutoCorrect,
    );

    $model = new ModelWithAutocorrect(['name' => 'lowercase']);
    $invariantWithIgnored = $invariant->on($model);
    $invariantWithIgnored->setIgnored(['description']);

    // This should not throw because 'description' is ignored
    $invariantWithIgnored->assert();
    expect(true)->toBeTrue(); // If no exception is thrown, the test passes
});

test('AutoCorrect policy can fix a violated invariant', function () {
    $entity = ModelWithAutocorrect::create(['name' => 'lowercase'])->asEntity();

    expect($entity->name)->toBe('Lowercase');
});

test('auto correct policy throws if subject is null when asserting', function () {
    $reflection = new \ReflectionClass(Invariant::class);
    $method = $reflection->getMethod('handleAutoCorrect');
    $method->setAccessible(true);
    $method->invoke(new Invariant(
        rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: ucfirst('lowercase'),
        touches: ['name'],
        policy: HydrationPolicy::AutoCorrect,
    ), null);
})->throws(\RuntimeException::class, 'AutoCorrect policy requires a subject and property to correct.');

test('auto correct policy throws if property does not exist on subject', function () {
    $entity = ModelWithAutocorrect::create(['name' => 'lowercase'])->asEntity();
    $invariant = Invariant::make(
        rule: fn ($value) => $value !== '' && ctype_upper($value[0]),
        message: 'Name must start with a capital letter',
        default: ucfirst('lowercase'),
        touches: ['nonExistentProperty'],
        policy: HydrationPolicy::AutoCorrect,
    )->on($entity);

    $invariant->assert();
})->throws(\RuntimeException::class, 'Property nonExistentProperty does not exist on the subject.');

test('handleQuarantine sets the entity to quarantined state when invariant is violated', function () {
    $entity = ModelWithQuarantine::violating()->asEntity();

    expect($entity->isQuarantined())->toBeTrue();
});

test('handleQuarantine throws if subject is null when asserting', function () {
    $reflection = new \ReflectionClass(Invariant::class);
    $method = $reflection->getMethod('handleQuarantine');
    $method->setAccessible(true);
    $method->invoke(new Invariant(
        rule: fn () => false,
        message: 'Invariant failed',
        default: null,
        touches: ['name'],
        policy: HydrationPolicy::Quarantine,
    ), null);
})->throws(\RuntimeException::class, 'Quarantine policy requires a subject implementing EnforcesInvariants.');

test('handleQuarantine throws if subject does not implement EnforcesInvariants', function () {
    $reflection = new \ReflectionClass(Invariant::class);
    $method = $reflection->getMethod('handleQuarantine');
    $method->setAccessible(true);
    $method->invoke(new Invariant(
        rule: fn () => false,
        message: 'Invariant failed',
        default: null,
        touches: ['name'],
        policy: HydrationPolicy::Quarantine,
    ), new class {});
})->throws(\TypeError::class);

test('')
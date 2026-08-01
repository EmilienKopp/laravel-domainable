<?php

namespace Splitstack\Domainable\Tests\Benchmark;

use Splitstack\Domainable\Repository\BaseRepository;
use Splitstack\Domainable\Tests\Fixtures\ExampleModel;
use Splitstack\Domainable\Tests\Fixtures\ProxyOnlyModel;

/**
 * These are not assertions about wall-clock time (that would flake on CI). They
 * seed a realistic dataset, time the entity path against the raw-model path, and
 * print the breakdown so you can eyeball whether assertInvariants() or the proxy
 * dispatch add any consistent overhead.
 *
 * Run with: composer bench
 */

/**
 * Best-of-N timer. Takes the fastest round to cut GC / scheduler noise.
 */
function bench(callable $fn, int $rounds = 5): float
{
    $best = INF;

    for ($i = 0; $i < $rounds; $i++) {
        $start = hrtime(true);
        $fn();
        $best = min($best, hrtime(true) - $start);
    }

    return $best / 1e9; // seconds
}

function report(string $title, array $rows): void
{
    $line = str_repeat('-', 64);
    fwrite(STDERR, "\n{$line}\n{$title}\n{$line}\n");

    foreach ($rows as $label => $value) {
        fwrite(STDERR, sprintf("  %-38s %s\n", $label, $value));
    }

    fwrite(STDERR, "{$line}\n");
}

function seedExampleModels(int $count): void
{
    ExampleModel::query()->delete();

    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        // name length > 5 so the nameIsLongEnough invariant passes
        $rows[] = ['name' => 'Record'.str_pad((string) $i, 6, '0', STR_PAD_LEFT)];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        ExampleModel::insert($chunk);
    }
}

test('all(): assertInvariants() overhead over a few thousand records', function () {
    $count = 2000;
    seedExampleModels($count);

    // Warm the reflection cache so the first-scan cost doesn't land on one row.
    (new ExampleModel(['name' => 'warmup00']))->asEntity();

    $raw = bench(fn () => ExampleModel::all()->all());
    $withAssert = bench(fn () => ExampleModel::all()->map(fn ($m) => $m->asEntity(true))->all());
    $noAssert = bench(fn () => ExampleModel::all()->map(fn ($m) => $m->asEntity(false))->all());

    $us = fn (float $s) => sprintf('%8.3f ms  (%6.2f us/record)', $s * 1e3, $s / $count * 1e6);

    report("all() over {$count} records — hydration overhead", [
        'raw ExampleModel::all()' => $us($raw),
        'asEntity(assertInvariants: false)' => $us($noAssert),
        'asEntity(assertInvariants: true)' => $us($withAssert),
        'proxy cost (noAssert - raw)' => sprintf('%8.3f ms', ($noAssert - $raw) * 1e3),
        'assertInvariants cost (with - noAssert)' => sprintf('%8.3f ms', ($withAssert - $noAssert) * 1e3),
        'assert overhead vs raw' => sprintf('%6.1f%%', $raw > 0 ? ($withAssert - $raw) / $raw * 100 : 0),
    ]);

    // Correctness sanity only — never assert on timing.
    expect(ExampleModel::all()->map(fn ($m) => $m->asEntity())->count())->toBe($count);
})->group('benchmark');

test('proxied method call vs direct model call in a loop', function () {
    $loops = 100_000;

    // With an invariant: entity->rename() runs assertInvariants() after each call,
    // model->rename() does not. This is the honest "entity vs model" comparison.
    $model = new ExampleModel(['name' => 'ValidName']);
    $entity = $model->asEntity();

    $modelDirect = bench(function () use ($model, $loops) {
        for ($i = 0; $i < $loops; $i++) {
            $model->rename('ValidName');
        }
    });

    $entityProxied = bench(function () use ($entity, $loops) {
        for ($i = 0; $i < $loops; $i++) {
            $entity->rename('ValidName');
        }
    });

    // No invariant: isolates the pure __call proxy dispatch from the invariant work.
    $plain = new ProxyOnlyModel(['name' => 'ValidName']);
    $plainEntity = $plain->asEntity();

    $plainDirect = bench(function () use ($plain, $loops) {
        for ($i = 0; $i < $loops; $i++) {
            $plain->rename('ValidName');
        }
    });

    $plainProxied = bench(function () use ($plainEntity, $loops) {
        for ($i = 0; $i < $loops; $i++) {
            $plainEntity->rename('ValidName');
        }
    });

    $ns = fn (float $s) => sprintf('%7.1f ms  (%5.1f ns/call)', $s * 1e3, $s / $loops * 1e9);

    report("method calls x {$loops}", [
        'model->rename() direct' => $ns($modelDirect),
        'entity->rename() (proxy + 1 invariant)' => $ns($entityProxied),
        'model->rename() direct (no invariant)' => $ns($plainDirect),
        'entity->rename() (proxy only, no inv.)' => $ns($plainProxied),
        'proxy dispatch overhead / call' => sprintf('%5.1f ns', ($plainProxied - $plainDirect) / $loops * 1e9),
        'invariant overhead / call' => sprintf('%5.1f ns', ($entityProxied - $plainProxied) / $loops * 1e9),
    ]);

    expect($entityProxied)->toBeGreaterThan(0.0);
})->group('benchmark');

<?php

use Illuminate\Support\Facades\Artisan;
use Splitstack\Domainable\Tests\Fixtures\Product;

test('entity:interface prints the generated contract', function () {
    $code = Artisan::call('entity:interface', ['model' => Product::class]);

    expect($code)->toBe(0)
        ->and(Artisan::output())
        ->toContain('interface ProductEntity')
        ->toContain('public function reprice(int $price, bool $notify = true): void;')
        ->toContain('public function activate(): self;');
});

test('entity:interface fails cleanly for an unknown model', function () {
    $code = Artisan::call('entity:interface', ['model' => 'App\\Nope']);

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('Model class not found');
});

test('entity:annotations prints a typed asEntity() docblock', function () {
    $code = Artisan::call('entity:annotations', [
        'model' => Product::class,
        '--entity' => 'App\\Entities\\ProductEntity',
    ]);

    expect($code)->toBe(0)
        ->and(Artisan::output())
        ->toContain('@property-read int $price')
        ->toContain('@method \App\Entities\ProductEntity asEntity()');
});

test('entity:interface --write creates the file, then cleans up', function () {
    $path = dirname((new ReflectionClass(Product::class))->getFileName()).'/ProductEntity.php';

    try {
        $code = Artisan::call('entity:interface', ['model' => Product::class, '--write' => true]);

        expect($code)->toBe(0)
            ->and($path)->toBeFile()
            ->and(file_get_contents($path))->toContain('interface ProductEntity');
    } finally {
        @unlink($path);
    }
});

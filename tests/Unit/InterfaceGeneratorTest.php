<?php

use Splitstack\Domainable\Codegen\InterfaceGenerator;
use Splitstack\Domainable\Tests\Fixtures\Product;

test('it generates an interface named after the model plus suffix', function () {
    $file = (new InterfaceGenerator)->generate(Product::class);

    expect($file->contents)
        ->toContain('namespace Splitstack\Domainable\Tests\Fixtures;')
        ->toContain('interface ProductEntity')
        ->and($file->path)->toEndWith('ProductEntity.php');
});

test('it exposes attributes as @property-read with cast-derived types', function () {
    $contents = (new InterfaceGenerator)->generate(Product::class)->contents;

    expect($contents)
        ->toContain('@property-read int $price')
        ->toContain('@property-read bool $active')
        ->toContain('@property-read mixed $name');
});

test('it copies #[Domain] method signatures including defaults', function () {
    $contents = (new InterfaceGenerator)->generate(Product::class)->contents;

    expect($contents)->toContain('public function reprice(int $price, bool $notify = true): void;');
});

test('a fluent domain method returns self, meaning the entity', function () {
    $contents = (new InterfaceGenerator)->generate(Product::class)->contents;

    expect($contents)->toContain('public function activate(): self;');
});

test('non-domain and invariant methods are not exposed', function () {
    $contents = (new InterfaceGenerator)->generate(Product::class)->contents;

    expect($contents)
        ->not->toContain('priceIsNonNegative')
        ->not->toContain('function save');
});

test('the generated interface is valid php', function () {
    $file = (new InterfaceGenerator(namespace: 'Generated\Tmp'))->generate(Product::class);

    expect(fn () => eval(substr($file->contents, strlen('<?php'))))->not->toThrow(ParseError::class);
});

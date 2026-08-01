<?php

use Splitstack\Domainable\Codegen\AnnotationGenerator;
use Splitstack\Domainable\Entity;
use Splitstack\Domainable\Tests\Fixtures\Product;

test('the docblock carries property-read lines and a typed asEntity()', function () {
    $docblock = (new AnnotationGenerator)->docblock(Product::class);

    expect($docblock)
        ->toContain(AnnotationGenerator::MARKER)
        ->toContain('@property-read int $price')
        ->toContain('@property-read bool $active')
        ->toContain('@method \\'.Entity::class.' asEntity()');
});

test('asEntity() is typed to a given entity interface when provided', function () {
    $docblock = (new AnnotationGenerator)->docblock(Product::class, 'App\\Entities\\ProductEntity');

    expect($docblock)->toContain('@method \App\Entities\ProductEntity asEntity()');
});

test('injecting adds the block above the class', function () {
    $source = <<<'PHP'
    <?php

    namespace Demo;

    use Illuminate\Database\Eloquent\Model;

    class Widget extends Model
    {
    }
    PHP;

    $generator = new AnnotationGenerator;
    $reflection = new ReflectionClass($generator);
    $inject = $reflection->getMethod('inject');

    $docblock = "/**\n * ".AnnotationGenerator::MARKER."\n * @property-read int \$id\n */";
    $once = $inject->invoke($generator, $source, $docblock, 'Widget');

    expect($once)->toContain($docblock)
        ->and($once)->toContain($docblock."\nclass Widget");
});

test('injecting twice replaces the block instead of duplicating it', function () {
    $source = <<<'PHP'
    <?php

    namespace Demo;

    class Widget
    {
    }
    PHP;

    $generator = new AnnotationGenerator;
    $inject = (new ReflectionClass($generator))->getMethod('inject');

    $blockA = "/**\n * ".AnnotationGenerator::MARKER."\n * @property-read int \$id\n */";
    $blockB = "/**\n * ".AnnotationGenerator::MARKER."\n * @property-read string \$id\n */";

    $once = $inject->invoke($generator, $source, $blockA, 'Widget');
    $twice = $inject->invoke($generator, $once, $blockB, 'Widget');

    expect(substr_count($twice, AnnotationGenerator::MARKER))->toBe(1)
        ->and($twice)->toContain('@property-read string $id')
        ->and($twice)->not->toContain('@property-read int $id');
});

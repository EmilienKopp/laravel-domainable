<?php

namespace Splitstack\Domainable\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static bool $booted = false;

    protected function setUp(): void
    {
        parent::setUp();

        static::bootEloquent();
        static::bootFacades();

        $this->createSchema();
    }

    protected static function bootEloquent(): void
    {
        if (static::$booted) {
            return;
        }

        $capsule = new Capsule;

        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        static::$booted = true;
    }

    protected static function bootFacades(): void
    {
        $capsule = new Capsule;
        $container = $capsule->getContainer();
        $container->singleton('validator', fn ($app) => new ValidationFactory(new Translator(new ArrayLoader, 'en'), $app)
        );
        Facade::setFacadeApplication($container);
    }

    protected function createSchema(): void
    {
        if (Capsule::schema()->hasTable('example_models') && Capsule::schema()->hasTable('products')) {
            return;
        }

        Capsule::schema()->create('example_models', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        Capsule::schema()->create('products', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('price');
            $table->boolean('active')->default(false);
            $table->timestamps();
        });

        Capsule::schema()->create('model_with_quarantines', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
    }
}

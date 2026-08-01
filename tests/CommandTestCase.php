<?php

namespace Splitstack\Domainable\Tests;

use Orchestra\Testbench\TestCase as Testbench;
use Splitstack\Domainable\DomainableServiceProvider;

/**
 * Boots a real Laravel app via Testbench so the Artisan commands can be tested
 * through $this->artisan(), the same way they run in a host application.
 */
abstract class CommandTestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [DomainableServiceProvider::class];
    }
}

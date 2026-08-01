<?php

namespace Splitstack\Domainable;

use Illuminate\Support\ServiceProvider;
use Splitstack\Domainable\Codegen\Commands\GenerateEntityAnnotations;
use Splitstack\Domainable\Codegen\Commands\GenerateEntityInterface;

class DomainableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateEntityInterface::class,
                GenerateEntityAnnotations::class,
            ]);
        }
    }
}

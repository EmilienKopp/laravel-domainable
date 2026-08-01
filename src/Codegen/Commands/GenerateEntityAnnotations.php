<?php

namespace Splitstack\Domainable\Codegen\Commands;

use Illuminate\Console\Command;
use Splitstack\Domainable\Codegen\AnnotationGenerator;

class GenerateEntityAnnotations extends Command
{
    protected $signature = 'entity:annotations
        {model : Fully qualified model class}
        {--entity= : Entity type returned by asEntity(), e.g. a generated interface}
        {--write : Rewrite the model file in place (otherwise the docblock is printed)}';

    protected $description = 'Annotate a model with @property-read and a typed asEntity() (ide-helper style)';

    public function handle(): int
    {
        $model = ltrim($this->argument('model'), '\\');

        if (! class_exists($model)) {
            $this->error("Model class not found: {$model}");

            return self::FAILURE;
        }

        $entity = $this->option('entity') ? ltrim($this->option('entity'), '\\') : null;
        $generator = new AnnotationGenerator;

        if ($this->option('write')) {
            $file = $generator->generate($model, $entity);
            $file->write();
            $this->info("Annotated {$file->path}");

            return self::SUCCESS;
        }

        $this->line($generator->docblock($model, $entity));
        $this->newLine();
        $this->comment('Pass --write to inject this above the class.');

        return self::SUCCESS;
    }
}

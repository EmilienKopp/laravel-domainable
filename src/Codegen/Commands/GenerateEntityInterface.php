<?php

namespace Splitstack\Domainable\Codegen\Commands;

use Illuminate\Console\Command;
use Splitstack\Domainable\Codegen\GeneratedFile;
use Splitstack\Domainable\Codegen\InterfaceGenerator;

class GenerateEntityInterface extends Command
{
    protected $signature = 'entity:interface
        {model : Fully qualified model class}
        {--namespace= : Namespace for the generated interface (defaults to the model namespace)}
        {--suffix=Entity : Suffix appended to the model name}
        {--path= : Override the output file path}
        {--write : Write the file (otherwise the code is printed)}';

    protected $description = 'Generate the opt-in domain entity interface for a model';

    public function handle(): int
    {
        $model = ltrim($this->argument('model'), '\\');

        if (! class_exists($model)) {
            $this->error("Model class not found: {$model}");

            return self::FAILURE;
        }

        $file = (new InterfaceGenerator(
            namespace: $this->option('namespace') ?: null,
            suffix: (string) $this->option('suffix'),
        ))->generate($model);

        if ($path = $this->option('path')) {
            $file = new GeneratedFile($path, $file->contents);
        }

        if ($this->option('write')) {
            $file->write();
            $this->info("Wrote {$file->path}");
            $this->line("Next: add `implements ...` to {$model} and type asEntity() to return it.");

            return self::SUCCESS;
        }

        $this->line($file->contents);

        return self::SUCCESS;
    }
}

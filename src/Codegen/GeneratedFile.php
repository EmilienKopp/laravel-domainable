<?php

namespace Splitstack\Domainable\Codegen;

/**
 * A file a generator wants to write: where it goes and what it holds.
 */
final class GeneratedFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $contents,
    ) {}

    public function write(): void
    {
        file_put_contents($this->path, $this->contents);
    }
}

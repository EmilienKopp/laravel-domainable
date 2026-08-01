<?php

namespace Splitstack\Domainable\Codegen;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Splitstack\Domainable\Entity;

/**
 * Option B: annotate the model in place, ide-helper style.
 *
 * Adds a marker-tagged docblock above the class with @property-read for the
 * model's attributes and a typed @method asEntity(). No new type to wire; run
 * it again any time and the marked block is replaced, not duplicated.
 */
final class AnnotationGenerator
{
    use BuildsDomainTypes;

    public const MARKER = '@generated-entity-annotations';

    /**
     * Build the docblock the model should carry.
     *
     * @param  class-string<Model>  $modelClass
     * @param  class-string|null  $entityType  return type for asEntity(), e.g. a generated interface
     */
    public function docblock(string $modelClass, ?string $entityType = null): string
    {
        $model = (new ReflectionClass($modelClass))->newInstance();

        $lines = ['/**', ' * '.self::MARKER];

        foreach ($this->attributeTypes($model) as $property => $type) {
            $lines[] = " * @property-read {$type} \${$property}";
        }

        $return = $entityType !== null ? '\\'.ltrim($entityType, '\\') : '\\'.Entity::class;
        $lines[] = " * @method {$return} asEntity()";
        $lines[] = ' */';

        return implode("\n", $lines);
    }

    /**
     * Return the model's source with the docblock injected or refreshed.
     *
     * @param  class-string<Model>  $modelClass
     * @param  class-string|null  $entityType
     */
    public function generate(string $modelClass, ?string $entityType = null): GeneratedFile
    {
        $class = new ReflectionClass($modelClass);
        $path = (string) $class->getFileName();

        return new GeneratedFile(
            path: $path,
            contents: $this->inject(
                (string) file_get_contents($path),
                $this->docblock($modelClass, $entityType),
                $class->getShortName(),
            ),
        );
    }

    private function inject(string $source, string $docblock, string $shortName): string
    {
        $class = '(?:final\s+|abstract\s+|readonly\s+)*class\s+'.preg_quote($shortName, '/').'\b';

        // Replace a previously generated block if present, otherwise insert.
        $existing = '/\/\*\*(?:(?!\*\/).)*?'.preg_quote(self::MARKER, '/').'.*?\*\/\s*(?='.$class.')/s';
        if (preg_match($existing, $source) === 1) {
            return (string) preg_replace($existing, $docblock."\n", $source, 1);
        }

        return (string) preg_replace('/('.$class.')/', $docblock."\n$1", $source, 1);
    }
}

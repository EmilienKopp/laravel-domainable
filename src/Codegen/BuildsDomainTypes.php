<?php

namespace Splitstack\Domainable\Codegen;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Splitstack\Domainable\Attributes\Domain;

/**
 * Shared reflection used by both generators: find the #[Domain] methods, turn
 * a model's casts into readable property types, and render method signatures
 * back into source. A model's `self`/`static` returns map to the entity, since
 * the proxy re-wraps a returned model as the entity.
 */
trait BuildsDomainTypes
{
    /**
     * @return list<ReflectionMethod>
     */
    protected function domainMethods(ReflectionClass $class): array
    {
        $methods = [];

        foreach ($class->getMethods() as $method) {
            if ($method->getAttributes(Domain::class) !== []) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * Attribute name => PHP type, drawn from casts, fillable and the key.
     *
     * @return array<string, string>
     */
    protected function attributeTypes(Model $model): array
    {
        $types = [];

        foreach ($model->getFillable() as $name) {
            $types[$name] = 'mixed';
        }

        foreach ($model->getCasts() as $name => $cast) {
            $types[$name] = $this->castToType($cast);
        }

        $key = $model->getKeyName();
        if (! isset($types[$key])) {
            $types[$key] = $model->getKeyType() === 'int' ? 'int' : 'string';
        }

        ksort($types);

        return $types;
    }

    protected function methodSignature(ReflectionMethod $method): string
    {
        $params = array_map(
            fn (ReflectionParameter $p) => $this->parameter($p),
            $method->getParameters(),
        );

        $return = $this->typeString($method->getReturnType());
        $return = $return !== '' ? ': '.$return : '';

        return sprintf('public function %s(%s)%s;', $method->getName(), implode(', ', $params), $return);
    }

    private function parameter(ReflectionParameter $param): string
    {
        $type = $this->typeString($param->getType());
        $out = $type !== '' ? $type.' ' : '';

        if ($param->isPassedByReference()) {
            $out .= '&';
        }

        if ($param->isVariadic()) {
            $out .= '...';
        }

        $out .= '$'.$param->getName();

        if (! $param->isVariadic() && $param->isDefaultValueAvailable()) {
            $out .= ' = '.$this->exportDefault($param->getDefaultValue());
        }

        return $out;
    }

    private function typeString(?ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(fn (ReflectionType $t) => $this->memberType($t), $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(fn (ReflectionType $t) => $this->memberType($t), $type->getTypes()));
        }

        /** @var ReflectionNamedType $type */
        $bare = $this->bareType($type);
        $name = strtolower($type->getName());

        if ($type->allowsNull() && $name !== 'null' && $name !== 'mixed') {
            return '?'.$bare;
        }

        return $bare;
    }

    /**
     * A member of a union or intersection: another intersection (DNF) or a
     * plain named type.
     */
    private function memberType(ReflectionType $type): string
    {
        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(fn (ReflectionType $t) => $this->memberType($t), $type->getTypes()));
        }

        /** @var ReflectionNamedType $type */
        return $this->bareType($type);
    }

    private function bareType(ReflectionNamedType $type): string
    {
        $name = $type->getName();

        if (in_array(strtolower($name), ['self', 'static', '$this'], true)) {
            return 'self';
        }

        return $type->isBuiltin() ? $name : '\\'.ltrim($name, '\\');
    }

    private function castToType(string $cast): string
    {
        $base = explode(':', $cast)[0];

        return match (strtolower($base)) {
            'int', 'integer', 'timestamp' => 'int',
            'real', 'float', 'double' => 'float',
            'decimal' => 'string',
            'string', 'encrypted' => 'string',
            'bool', 'boolean' => 'bool',
            'array', 'json' => 'array',
            'object' => 'object',
            'collection' => '\\Illuminate\\Support\\Collection',
            'date', 'datetime', 'custom_datetime', 'immutable_date', 'immutable_datetime' => '\\Illuminate\\Support\\Carbon',
            default => class_exists($base) ? '\\'.ltrim($base, '\\') : 'mixed',
        };
    }

    private function exportDefault(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return "'".str_replace("'", "\\'", $value)."'";
        }

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = (is_int($key) ? '' : $this->exportDefault($key).' => ').$this->exportDefault($item);
            }

            return '['.implode(', ', $parts).']';
        }

        return 'null';
    }
}

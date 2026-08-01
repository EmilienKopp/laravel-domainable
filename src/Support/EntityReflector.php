<?php

namespace Splitstack\Domainable\Support;

use ReflectionClass;
use ReflectionNamedType;
use Splitstack\Domainable\Attributes\Domain;
use Splitstack\Domainable\Attributes\Enforces;
use Splitstack\Domainable\Data\Invariant;

/**
 * Caches the surface of a model that is exposed through its #[Domain] and #[Enforces] attributes.
 *
 * @phpstan-type EntityMeta array{domain: list<string>, invariants: array<string, string>}
 */
final class EntityReflector
{
    /** @var array<class-string, EntityMeta> */
    private static array $cache = [];

    /**
     * @return EntityMeta
     */
    public static function scan(object $model): array
    {
        $class = $model::class;

        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $domain = [];
        $invariants = [];
        $ref = new ReflectionClass($model);

        foreach ($ref->getMethods() as $method) {
            if ($method->getAttributes(Domain::class) !== []) {
                $domain[] = $method->getName();
            }

            if ($method->getReturnType() instanceof ReflectionNamedType && $method->getReturnType()->getName() === Invariant::class) {
                $invariants[$method->getName()] = $method->getName();
            }
        }

        return self::$cache[$class] = [
            'domain' => $domain,
            'invariants' => $invariants,
        ];
    }
}

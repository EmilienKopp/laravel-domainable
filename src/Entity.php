<?php

namespace Splitstack\Domainable;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Contracts\ProvidesEntity;

final class Entity
{
    private const ALLOWED_METHODS = ['clone'];

    /**
     * @param  list<string>  $domain  method names exposed as domain behavior
     */
    public function __construct(
        private readonly Model&ProvidesEntity $model,
        private readonly array $domain,
    ) {}

    public function create(array $attributes): self
    {
        $model = $this->model->create($attributes);

        return new self($model, $this->domain);
    }

    public function __get(string $name): mixed
    {
        return $this->model->getAttribute($name);
    }

    public function __isset(string $name): bool
    {
        return $this->model->getAttribute($name) !== null;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (! in_array($name, $this->domain, true) && ! in_array($name, self::ALLOWED_METHODS, true)) {
            throw new BadMethodCallException("Not domain behavior: {$name}");
        }

        $result = $this->model->{$name}(...$arguments);

        $this->assertInvariants();

        // return the entity instead of the model if the original method reurns a model
        return $result === $this->model ? $this : $result;
    }

    public function assertInvariants(): void
    {
        $this->model->assertInvariants();
    }

    public function isQuarantined(): bool
    {
        return $this->model->isQuarantined();
    }

    public function toModel(): Model
    {
        return $this->model;
    }
}

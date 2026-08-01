<?php

namespace Splitstack\Domainable\Repository;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Domainable\Contracts\ProvidesEntity;
use Splitstack\Domainable\Entity;

class BaseRepository
{
    /**
     * @property class-string<Model&ProvidesEntity> $for
     */
    protected string $for;

    /**
     * @property Model&ProvidesEntity $model
     */
    protected Model&ProvidesEntity $model;

    public function __construct()
    {
        if (! is_subclass_of($this->for, Model::class)) {
            throw new \LogicException("Repository for {$this->for} must be an Eloquent model");
        }

        if (! is_subclass_of($this->for, ProvidesEntity::class)) {
            throw new \LogicException("Repository for {$this->for} must implement ProvidesEntity");
        }

        /** @var Model&ProvidesEntity $model */
        $this->model = new $this->for;
    }

    /**
     * @param  bool  $fetchUnsafe  skip the invariant check on hydration so an
     *                             entity in an invalid state loads instead of
     *                             throwing. For inspection or repair only.
     */
    public function find(int|string $id, bool $nullIfQuarantined = false, bool $fetchUnsafe = false): ?Entity
    {
        /** @var Model&ProvidesEntity|null $model */
        $model = $this->model->find($id);

        $entity = $model?->asEntity(assertInvariants: ! $fetchUnsafe);

        if ($nullIfQuarantined && $entity?->isQuarantined()) {
            return null;
        }

        return $entity;
    }

    /**
     * @return list<Entity>
     */
    public function all(): array
    {
        return $this->model->all()
            ->map(fn (Model&ProvidesEntity $model) => $model->asEntity())
            ->filter(fn (Entity $entity) => ! $entity->isQuarantined())
            ->values()
            ->all();
    }

    /**
     * @return list<Entity>
     */
    public function withQuarantined(): array
    {
        return $this->model->all()
            ->map(fn (Model&ProvidesEntity $model) => $model->asEntity())
            ->values()
            ->all();
    }

    public function save(Entity $entity): void
    {
        $model = $entity->toModel();

        // TypeErrors will prevent from ever getting anything but an eloquent model here

        $model->save();
    }
}

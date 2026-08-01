<?php

namespace Splitstack\Domainable\Contracts;

use Splitstack\Domainable\Entity;

interface Repository
{
    public function find(int|string $id): ?Entity;

    /**
     * @return list<Entity>
     */
    public function all(): array;

    public function save(Entity $entity): void;

    public function delete(Entity $entity): void;
}

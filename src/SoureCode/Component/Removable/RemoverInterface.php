<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable;

interface RemoverInterface
{
    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @throws \LogicException
     */
    public function remove(object $entity, bool $soft = true, bool $flush = true): void;

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @throws \LogicException
     */
    public function restore(object $entity, bool $flush = true): void;
}

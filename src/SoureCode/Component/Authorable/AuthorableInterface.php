<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable;

interface AuthorableInterface
{
    public function getCreatedBy(): ?object;

    /**
     * @template T of object
     *
     * @param T $author
     */
    public function setCreatedBy(object $author): void;

    public function getUpdatedBy(): ?object;

    /**
     * @template T of object
     *
     * @param T $author
     */
    public function setUpdatedBy(object $author): void;
}

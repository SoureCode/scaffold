<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Author;

/**
 * Returns the user who initiated a switch_user impersonation, or null when
 * the current request is NOT impersonated. The bundle ships a Symfony-Security
 * adapter; consumers wiring a custom AuthorProvider can supply their own.
 */
interface ImpersonatorProviderInterface
{
    public function getImpersonator(): ?object;
}

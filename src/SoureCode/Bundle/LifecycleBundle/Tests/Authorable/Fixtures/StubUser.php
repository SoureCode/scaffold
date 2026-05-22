<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures;

use Symfony\Component\Security\Core\User\UserInterface;

final class StubUser implements UserInterface
{
    public function __construct(
        private readonly string $identifier,
    ) {
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}

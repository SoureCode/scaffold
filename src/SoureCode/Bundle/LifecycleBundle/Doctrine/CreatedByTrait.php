<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Doctrine;

use SoureCode\Component\Lifecycle\Attribute\CreatedBy;
use Symfony\Component\Security\Core\User\UserInterface;

trait CreatedByTrait
{
    #[CreatedBy]
    private ?UserInterface $createdBy = null;

    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserInterface $user): void
    {
        $this->createdBy = $user;
    }
}

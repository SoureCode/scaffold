<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Doctrine;

use SoureCode\Component\Lifecycle\Attribute\UpdatedBy;
use Symfony\Component\Security\Core\User\UserInterface;

trait UpdatedByTrait
{
    #[UpdatedBy]
    private ?UserInterface $updatedBy = null;

    public function getUpdatedBy(): ?UserInterface
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?UserInterface $user): void
    {
        $this->updatedBy = $user;
    }
}

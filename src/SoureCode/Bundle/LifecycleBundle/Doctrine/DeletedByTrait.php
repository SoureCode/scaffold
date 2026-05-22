<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Doctrine;

use SoureCode\Component\Lifecycle\Attribute\DeletedBy;
use Symfony\Component\Security\Core\User\UserInterface;

trait DeletedByTrait
{
    #[DeletedBy]
    private ?UserInterface $deletedBy = null;

    public function getDeletedBy(): ?UserInterface
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?UserInterface $user): void
    {
        $this->deletedBy = $user;
    }
}

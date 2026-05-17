<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Doctrine;

use SoureCode\Component\Authorable\Attribute\CreatedBy;
use SoureCode\Component\Authorable\Attribute\UpdatedBy;
use Symfony\Component\Security\Core\User\UserInterface;

trait AuthorableTrait
{
    #[CreatedBy]
    private ?UserInterface $createdBy = null;

    #[UpdatedBy]
    private ?UserInterface $updatedBy = null;

    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserInterface $user): void
    {
        $this->createdBy = $user;
    }

    public function getUpdatedBy(): ?UserInterface
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?UserInterface $user): void
    {
        $this->updatedBy = $user;
    }
}

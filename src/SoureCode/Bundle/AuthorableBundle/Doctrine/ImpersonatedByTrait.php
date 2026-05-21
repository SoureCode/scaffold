<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Doctrine;

use SoureCode\Component\Authorable\Attribute\ImpersonatedBy;
use Symfony\Component\Security\Core\User\UserInterface;

trait ImpersonatedByTrait
{
    #[ImpersonatedBy]
    private ?UserInterface $impersonatedBy = null;

    public function getImpersonatedBy(): ?UserInterface
    {
        return $this->impersonatedBy;
    }

    public function setImpersonatedBy(?UserInterface $user): void
    {
        $this->impersonatedBy = $user;
    }
}

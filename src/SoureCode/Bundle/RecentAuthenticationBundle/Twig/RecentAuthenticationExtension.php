<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Twig;

use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use Twig\Attribute\AsTwigFunction;

final class RecentAuthenticationExtension
{
    public function __construct(
        private readonly RecentAuthentication $recentAuthentication,
    ) {}

    #[AsTwigFunction('is_authenticated_recently')]
    public function isAuthenticatedRecently(): bool
    {
        return $this->recentAuthentication->isActive();
    }
}

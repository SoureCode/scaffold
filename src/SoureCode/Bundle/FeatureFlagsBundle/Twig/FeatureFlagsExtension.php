<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Twig;

use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;
use Twig\Attribute\AsTwigFunction;

final class FeatureFlagsExtension
{
    public function __construct(
        private readonly FeatureFlagsManagerInterface $featureFlags,
    ) {}

    #[AsTwigFunction('feature_enabled')]
    public function featureEnabled(string $name): bool
    {
        return $this->featureFlags->isEnabled($name);
    }
}

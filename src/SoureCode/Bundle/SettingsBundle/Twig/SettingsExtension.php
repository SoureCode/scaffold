<?php

declare(strict_types=1);

namespace SoureCode\Bundle\SettingsBundle\Twig;

use SoureCode\Component\Settings\Manager\SettingsManagerInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class SettingsExtension
{
    public function __construct(
        private readonly SettingsManagerInterface $settings,
    ) {}

    #[AsTwigFunction('setting')]
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    #[AsTwigFilter('setting')]
    public function settingFilter(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }
}

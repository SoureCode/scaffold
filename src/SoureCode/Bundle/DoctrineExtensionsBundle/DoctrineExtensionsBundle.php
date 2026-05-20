<?php

declare(strict_types=1);

namespace SoureCode\Bundle\DoctrineExtensionsBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class DoctrineExtensionsBundle extends AbstractBundle
{
    /**
     * No semantic config — the bundle exposes a single shared service (`ChangeSetMatcher`)
     * and the test scaffold (`AbstractBundleTestCase`). `$config` is accepted only to satisfy
     * the `AbstractBundle::loadExtension` signature.
     *
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');
    }
}

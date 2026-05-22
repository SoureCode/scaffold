<?php

declare(strict_types=1);

use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Support\FixedImpersonatorProvider;
use SoureCode\Component\Lifecycle\Author\ImpersonatorProviderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

return static function (ContainerBuilder $container): void {
    $container->setDefinition(
        'app.impersonator_provider',
        (new Definition(FixedImpersonatorProvider::class))->setPublic(true),
    );

    $container->setAlias(ImpersonatorProviderInterface::class, 'app.impersonator_provider')->setPublic(true);
};

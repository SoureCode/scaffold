<?php

declare(strict_types=1);

use SoureCode\Bundle\AuthorableBundle\Tests\Support\FixedImpersonatorProvider;
use SoureCode\Component\Authorable\Author\ImpersonatorProviderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

return static function (ContainerBuilder $container): void {
    $container->setDefinition(
        'app.impersonator_provider',
        (new Definition(FixedImpersonatorProvider::class))->setPublic(true),
    );

    $container->setAlias(ImpersonatorProviderInterface::class, 'app.impersonator_provider')->setPublic(true);
};

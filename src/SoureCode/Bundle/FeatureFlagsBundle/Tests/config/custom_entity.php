<?php

declare(strict_types=1);

use SoureCode\Component\FeatureFlags\Tests\Fixtures\CustomFeatureFlag;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerBuilder $container): void {
    $container->loadFromExtension('feature_flags', [
        'entity_class' => CustomFeatureFlag::class,
        'table_name' => 'custom_flags',
    ]);
};

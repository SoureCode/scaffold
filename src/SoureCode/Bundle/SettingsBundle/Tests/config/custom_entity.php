<?php

declare(strict_types=1);

use SoureCode\Component\Settings\Tests\Fixtures\CustomSetting;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerBuilder $container): void {
    $container->loadFromExtension('settings', [
        'entity_class' => CustomSetting::class,
        'table_name' => 'custom_settings',
    ]);
};

<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

/**
 * @internal Maps an entity FQCN to its runtime-generated entity-proxy FQCN.
 */
final class EntityProxyNamer
{
    public const string NAMESPACE_PREFIX = 'SoureCode\\Versionable\\Generated\\Proxy';

    public static function proxyClassFor(string $originalClass): string
    {
        $original = ltrim($originalClass, '\\');
        $segments = explode('\\', $original);
        $shortName = array_pop($segments);
        $namespace = $segments === [] ? self::NAMESPACE_PREFIX : self::NAMESPACE_PREFIX . '\\' . implode('\\', $segments);

        return $namespace . '\\' . $shortName . 'VersionableProxy';
    }

    public static function fileNameFor(string $originalClass): string
    {
        return str_replace('\\', '_', ltrim($originalClass, '\\')) . 'VersionableProxy.php';
    }
}

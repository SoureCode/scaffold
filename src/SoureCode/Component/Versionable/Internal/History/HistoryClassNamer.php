<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

/**
 * Maps an entity FQCN to the generated *History class FQCN, deterministically,
 * so generator, factory and hydrator agree without coordinating state.
 */
final class HistoryClassNamer
{
    public const string NAMESPACE_PREFIX = 'SoureCode\\Versionable\\Generated';

    public static function historyClassFor(string $originalClass): string
    {
        $original = ltrim($originalClass, '\\');
        $segments = explode('\\', $original);
        $shortName = array_pop($segments);
        $namespace = $segments === [] ? self::NAMESPACE_PREFIX : self::NAMESPACE_PREFIX . '\\' . implode('\\', $segments);

        return $namespace . '\\' . $shortName . 'History';
    }

    public static function fileNameFor(string $originalClass): string
    {
        return str_replace('\\', '_', ltrim($originalClass, '\\')) . 'History.php';
    }
}

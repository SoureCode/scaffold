<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

abstract class AbstractSettingsManager implements SettingsManagerInterface
{
    protected const string KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]*$/';

    protected static function validateKey(string $key): void
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid settings key "%s": must match %s.',
                $key,
                self::KEY_PATTERN,
            ));
        }
    }
}

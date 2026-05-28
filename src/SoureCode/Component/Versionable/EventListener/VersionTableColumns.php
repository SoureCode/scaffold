<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\EventListener;

final class VersionTableColumns
{
    public const string ID = 'id';
    public const string ENTITY_ID = 'entity_id';
    public const string VERSION = 'version';
    public const string CREATED_AT = 'created_at';

    public const string JOIN_VERSION_ID = 'version_id';
    public const string JOIN_POSITION = 'position';
    public const string JOIN_TARGET_ID = 'target_id';
    public const string JOIN_TARGET_VERSION = 'target_version';

    /**
     * Doctrine's own default for an unsized discriminator string column;
     * used when the host's metadata doesn't override `discriminatorColumn['length']`.
     */
    public const int DEFAULT_DISCRIMINATOR_LENGTH = 255;

    private function __construct()
    {
    }
}

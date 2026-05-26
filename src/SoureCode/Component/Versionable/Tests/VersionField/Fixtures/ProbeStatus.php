<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\VersionField\Fixtures;

enum ProbeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

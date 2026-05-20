<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

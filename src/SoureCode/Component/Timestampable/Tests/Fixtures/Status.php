<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Tests\Fixtures;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
